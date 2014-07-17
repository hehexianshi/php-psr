#!/usr/bin/env php
<?php
$inputFileName = $argv[1];
$outputFileName = $argv[2];

$str = file_get_contents($inputFileName);

$constant = get_defined_constants(true);

function captureConstant($name = '')
{
    static $constants = array();
    if ($name == '') {
        return $constants; 
    }
    $name = str_replace(array("'", "\""), '', $name);
    $constants[] = $name;
}

function captureClass($name = '') 
{
    static $classes = array();
    if ($name == '') {
        return $classes;
    }

    $classes[] = $name;
}

function captureFunction($name = '')
{
    static $function = array();

    if ($name == '') {
        return $function;
    }
    $function[] = $name;
}

function addSemicolon($str, $type = 301) {
    // 不全 单行 大括号
    $token = token_get_all($str);
    $has = array();
    $rhas = array();
    foreach ($token as $k => $v) {
        if(is_array($v) && $v[0] == $type) {
            $i = 0;
            $s = 0;
            $t = 0;
            $oldStr = '';
            $replaceStr = '';
            $count = 0;
            while(true) {
                if(is_array($token[$k + $i])) {
                    $oldStr .= $token[$k + $i][1];
                }
                if(is_string($token[$k + $i])) {
                    $oldStr .= $token[$k + $i];
                }
                $i++;   
                if(is_string($token[$k + $i]) && $token[$k + $i] == ';') {
                    break;
                }
                if(is_string($token[$k + $i]) && $token[$k + $i] == '(') {
                    $s++;
                }
                if(is_string($token[$k + $i]) && $token[$k + $i] == ')') {
                    $s--;
                    if($s == 0) {
                        $count++;
                    }
                    if($s == 0 && $count == 1) {
                        $oldStr .= '#$REPLACE_POINT$#'; 
                    }
                }
                if(is_string($token[$k + $i]) && $token[$k + $i] == '{') {
                    $t++;
                }
            }

            if ($t) {
            
                $oldStr = str_replace('#$REPLACE_POINT$#', '', $oldStr);
            } else {
                $olddStr = $oldStr;

                $oldStr .= ';';

                $oldStr = str_replace('#$REPLACE_POINT$#', '', $oldStr);

                

                if(!array_search($oldStr, $rhas)) {
                    $has[$k]['r'] = $oldStr;
                }


                preg_match('/^'.$v[1].'(.*?)#\$REPLACE_POINT\$#/', $olddStr, $matches);
                $matches[0] = str_replace('#$REPLACE_POINT$#', '', $matches[0]);
                $matches[0] .= ')';

                if(!array_search($matches[0], $has)) {
                   $has[$k]['l'] = $matches[0];
                }

            } 
            
        } 
    }
    
    $has = array_values($has);
    foreach($has as $k => $v) {
        // 加右括号
        $str = str_replace($v['r'], $v['r'] . '}', $str);
        $w = str_replace($v['l'], '', $v['r']);
        $n = $v['l'] . '{' . $w;
        $str = str_replace($v['r'], $n, $str);
    }


    return $str;
}

// 计算数组 字符长度
function arrayWarp($k, $token) {

    $array_start = 0;
    $k++;
    $bracketsCount = 1;
    // 从'(' 开始
    $i = 0;
    $len = 0;
    
    while(true) {
        $i++;
        if(!$bracketsCount) {
            break;
        }
        if(is_string($token[$k + $i])) {
        
            $len += strlen($token[$k + $i]);
        }

        if(is_array($token[$k + $i])) {
            $len += strlen($token[$k + $i][1]);
        }

        if(is_string($token[$k + $i]) && $token[$k + $i] == '(') {
            $bracketsCount++;
            continue;
        }

        if(is_string($token[$k + $i]) && $token[$k + $i]  == ')') {
        
            $bracketsCount--;
            continue;
        }
    }

    if ($len > 70) {
        return true;
    } else {
        return false;    
    }
}


$str = addSemicolon($str);
$str = addSemicolon($str, 302);

$token = token_get_all($str);


foreach ($token as $k => $v) {
    if (is_array($v)) {
        $token[$k][3] = token_name($v[0]);
        if($token[$k][0] == 375)
            unset($token[$k]);
    
    }
}

$token = array_values($token);
$set = 0;
$name = '';
$data = '';
$line = 0;
$theBankToken = '';
$quoteCount = 0;
$bracketsCount = 0;
$arrayStart = 0;
// 如果数组过长  需要折行
$splitArray = 0;
$wrapLabel = array('abstract', 'interface', 'class', 'function', 'protected', 'public', 'private');
/** 
 *
 *   // 花括号 后面不换行
 * 一般情况下 只有 class  trait  function 的大括号 重起一行  其他的都不用
 *
 */
$wrapA = array('catch', 'while', 'else', 'elseif');
$wrapB = array('+', '-', '(');
// 由于array 有单独的类型行token 所以不再数组中
/**
 * 最常用的 (bool) $int 
 * 
 * function a(int $a) {}
 * 
 */
$ContentTable = array('bool', 'int', 'float', 'string', 'object', 'resource', 'callback', 'null');
/**
 * bool型的 需要全部转为小写
 */
$BoolTable = array('TRUE', 'FALSE');
// 记录do开始
$todoStart = 0;
// 记录switch 开始
$switchStart = 0;
// switch开始时的缩进个数
$switchStartInd = 0;
// 代码需要缩进的个数
$indentationNum = 0;
// && 需要换行时 
$andStart = 0;

foreach ($token as $k => $v) {

    if ($theBankToken == 'use' && is_string($v) && in_array($v, $wrapB)) {
        $theBankToken = '';
        $data .= PHP_EOL;
    
    }
    if (is_string($v)) {
        switch ($v) {
            case '=>':
            case '+':
            case '-':
                $data .= ' ' . $v . ' ';
                break;

            /**
             * 如果是function 参数的逗号 后面需要加空格
             * 但是 有一种例外  匿名函数的参数 逗号 有时候需要换行 忽略掉这种情况
             *
             * 数组也是使用逗号分割 如果数组太长  逗号后需要加换行符
             */
            case ',':
                if ($splitArray) {
                    $data .= $v . PHP_EOL. str_repeat('    ', $indentationNum + $bracketsCount);
                    break;
                }
                $data .= $v . ' ';
                break;
            /**
             * function d ()
             * {
             * }
             * ($a + $v) + $v
             *
             *
             */
            case '{':
                if ($quoteCount) {
                    $data .= $v; 
                    break;
                }

                if ($theBankToken == 'use') {
                    $indentationNum++;
                    $data .= ' ' . $v; 
                    break;
                }
                if(is_array($token[$k - 1]) && ($token[$k - 1][1] == 'else' || $token[$k - 1][1] == 'elseif' || $token[$k - 1][1] == 'do' || $token[$k - 1][1] == 'try')) {
                    $space = '';
                } elseif (is_string($token[$k - 1]) && $token[$k - 1] == ')' && !in_array(strtolower($theBankToken), $wrapLabel)) {
                    $space = '';
                } else {
                    $space = str_repeat('    ', $indentationNum);
                }
                $v = $space . $v;
                if (in_array(strtolower($theBankToken), $wrapLabel)) {
                    $data .= PHP_EOL . $v . "\n";
                    //$data .= $v;
                } elseif(is_array($token[$k + 1]) && $token[$k + 1][0] == 370) {
                    $data .= ' ' . $v;
                
                } else {
                    $data .= ' ' . $v . PHP_EOL;
                }
                $line--;
                $indentationNum++;

                break;
            case '}':
                if ($quoteCount) {
                    $data .= $v; 
                    break;
                }

                if( $switchStart && $indentationNum - 2  == $switchStartInd) {
                    $switchStart = 0;
                    $indentationNum--;
                    $space = str_repeat('    ', $indentationNum - 1);
                } else {
                
                    $space = str_repeat('    ', $indentationNum - 1);
                }
                $data .= PHP_EOL;
                $data .= $space;
                // }; 匿名函数
                if(isset($token[$k + 1]) && is_array($token[$k + 1]) && in_array($token[$k + 1][1], $wrapA)) {
                    if($token[$k + 1][1] == 'while') {
                        $data .= $v . ' ';
                    } else {
                        $data .= $v;
                    }
                } else {
                    $data .= $v . PHP_EOL;
                }
                $indentationNum--;
                break;
                /**
                 * 正常情况  对( 和 ) 不做任何处理 靠其他符号增加间距
                 * 
                 * 只有当是数组并且去要换行的时候 才需要换行 和 缩进
                 */
            case '(':
                
                // 记录数组 结束
                if($arrayStart) {
                    $bracketsCount++;
                }
                // check is a new line
                if ($splitArray) {
                    $data .= $v . PHP_EOL . str_repeat('    ', $indentationNum + $bracketsCount); 
                } else {
                    $data .= $v;
                }
                break;
            case ')':
                
                if($arrayStart) {
                    $bracketsCount--;
                }
                if ($splitArray) {
                    $data .= PHP_EOL . str_repeat('    ', $indentationNum + $bracketsCount) . $v;
                } else {
                    $data .= $v;
                }
                
                if($bracketsCount == 0) {
                    $arrayStart = 0;
                    $splitArray = 0;
                }
                break;
                /**
                 * case 1: 
                 * default :
                 * 
                 * $a = true ? 1 : 2;
                 *
                 */
            case ':':
                // case 需要缩进
                if($theBankToken == 'case' || $theBankToken == 'default') {
                    $indentationNum++; 
                }
                if (is_array($token[$k + 1]) && $token[$k + 1][0] == 329) {
                    $data .= $v . PHP_EOL;
                } elseif ($theBankToken == 'case' && $token[$k + 1][0] != 370) {
                    // 如果遇到为case 的情况 后面的需要换行
                    // 不能让case 后面的注释换行
                    $data .= $v . PHP_EOL; 
                } else {
                    $data .= ' ' . $v . ' ';
                }
                break;
                /**
                 *
                 * 1. for ($a = 1; $b = 2; ...)
                 * demo    demoasd  
                 * 2. echo $a;
                 *    echo $a;
                 * 3. echo $a; // echo $a;
                 */
            case ';':
                if ($theBankToken == 'for') {
                    $data .= $v . " ";
                } elseif(isset($token[$k + 1]) && is_array($token[$k + 1]) && $token[$k + 1][0] == 370) {
                    $data .= $v . ' ';

                } else {
                    $data .= $v . PHP_EOL;
                }
                break;
            case '*':
            case '/':
            case '%':
                $data .= ' ' . $v . ' ';
                break;
            /*
             * $a = $b, $b === $c, $v <= $c
             *
             */
            case '>':
            case '<':
            case '=':
            case '===':
            case '!=':
            case '<=':
            case '<==':
            case '>==':
            case '?':
            case ':':
            case '|':
            case '.':
                $data .= ' ' . $v . ' ';
                break;
            case '"':
                $data .= $v;
                $quoteCount ? $quoteCount-- : $quoteCount++;
                break;
            default:
                $data .= $v;
                break;

        }
        continue;
    }

    if ($theBankToken == 'use' && $v[1] != 'use' && $line < $v[2]) {
        $theBankToken = '';
        $data .= PHP_EOL;
    } 


    // 记录行首token
    if ($line < $v[2]) {

        if ($andStart) {
            $andStart = 0;
        } else {
            $r = str_repeat('    ', $indentationNum);
        }
    } else {
        $r = '';
    }


    // 捕获常量
    if ($v[0] == 307 && $v[1] == 'define') {
        captureConstant($token[$k + 2][1]);

    }

    // 捕获class
    if ($v[0] == 354 || $v[0] == 355) {
        captureClass($token[$k + 1][1]);
    
    }

    if ($v[0] == 334 && !is_string($token[$k + 1])) {
        captureFunction($token[$k + 1][1]);
        
    }


    switch ($v[0]) {
        case 372: //<?php
            $data .= $r . $v[1];
            break;
        case '309': //变量
            // 当为sql 的时候 变量 会和 上一个token 不在一行 
            if (is_string($token[$k - 1]) && $token[$k -1] == '.') {
                $data .= $v[1];
                break; 
            }

            $data .= $r . $v[1];
            break;
        case '334': //function
            $data .= $r . $v[1] . ' ';
            break;
        /**
         * 这些方法用法单一 一般都是在行首出现
         * 所以只要再行首 加上缩紧即可
         * 尾部 必须要有一个空格
         */
        case '345': //private
        case '344': //protected
        case '343': //public
        case '348': //static
        case '347': //abstract
        case '356': //interface
        case '354': //class
        case '355': //trait
        case '322': //foreach
        case '346': //final
            $data .= $r . $v[1] . ' ';
            break;
        case '317': //do
            $todoStart = 1;
            $data .= $r . $v[1];
            break;
        case '335':
            $data .= $r . $v[1] . ' ';
            break;

        /**
         *
         * 一般情况 extends implements 都在中间
         * use demo as d
         *
         * as 用于foreach
         * => 用于foreach 和 数组定义
         *
         */
        case '357': //extends
        case '358': //implements
        case '326': //as
        case '360': //=>
            $data .= $r . ' ' . $v[1] . ' ';
            break;
        case '340'://use
            // namespace
            /**
             * 1 .like this  use 必须要缩紧
             * trait demo{}
             *
             * class d
             * {
             *      use demo;
             * }
             *
             * 2 . namespace demo
             *     use demo
             *
             * 3 . $demo = function() use ($a) {var_dump($a);}
             *
             */
            if ($line != $v[2]) {
                // use 和 namespace 之间要有空行
                if ($theBankToken != $v[1]) {
                    $data .= PHP_EOL;
                }
                $data .= $r . $v[1] . ' ';
            } else {
                // 匿名函数  不规范
                $data .= $r . ' ' . $v[1] . ' ';
            }
            break;
        case '341': //insteadof
            $data .= ' ' . $v[1] . ' ';
            break;
        case '301': //if
            $data .= $r .$v[1] . ' ';
            break;
        case '302': //elseif
            $data .= ' ' . $v[1] . ' ';
            break;
        case '303': //else
            // 需要判断上一个是{ 
            if(is_string($token[$k - 1]) == '}' && is_string($token[$k + 1]) == '{') {
                $data .= ' ' . $v[1] . '';
            }
            //$data .= $r .$v[1] . ' ';
            break;
        case '381'://namespace
            $data .= $v[1] . ' ';
            break;
        /**
         * try{
         * 
         * } catch (Exception $e) {
         *
         * }
         */
        case '338': //catch
            $data .= ' ' . $v[1] . ' ';
            break;
        case '299':
            $data .= $v[1] . ' ';
            break;
        case '307':
            if (is_string($token[$k - 1]) && $token[$k - 1] == '.') {
                $data .= $v[1];
                break;
            }


            if (is_array($token[$k + 1]) && $token[$k + 1][0] == 309 && in_array($v[1], $ContentTable)) {
                //like this : function demo(int $a){} 
                $data .= $r . $v[1] . ' ';
                break;
                
            } elseif (is_array($token[$k + 1]) && $token[$k + 1][0] == 309) {
            
                $data .= $r . $v[1] . ' ';
                break;
            }

            if(strtolower($v[1]) == 'exception') {
                $data .= $r . $v[1] . ' ';
                break;
            }

            /**
             *
             * TRUE  ->  true
             *
             * FALSE ->  false
             *
             */

            if (in_array($v[1], $BoolTable)) {
                $data .= $r . strtolower($v[1]);
                break;
            }

            $data .= $r . $v[1];
            break;

            /**
             *
             * 像这样的注释
             */
        case '371': //T_DOC_DOCUMENT
            $data .= $r . $v[1] . PHP_EOL;
            break;

        case '370': //单行注释 /* */

            if ($theBankToken != $v[1] && $line == $v[2]) {
                // 如果是数组中 并且为splitArray模式的
                if ($splitArray) {
                    $data .= $v[1] . str_repeat('    ', $indentationNum + $bracketsCount);
                    break;
                }
                $data .= $v[1];
                break;
            }
            
            if (!is_array($token[$k - 1]) || $token[$k - 1][0] != $v[0]) {
                $data .= PHP_EOL;
            }

            if (strlen($v[1]) > 70) {
                // 如果长度过长
                $data .= $r . $v[1] . PHP_EOL;
                break;
            }
            // 如果连续多行注释 不能换行  #bug#
            $data .=  $r .  $v[1];
            break;
            /**
             *
             * return false;
             *
             * if ($a = 1) echo   ->     if ($a = 1) {
             *                               echo
             *                           }
             */
        case '336': //return
        case '316': //echo
            $data .= PHP_EOL . $r . $v[1] . ' ';
            break;
        case '283': // ==
        case '284': // >=
        case '278': // ||
            $data .= ' ' . $v[1] . ' ';
            break;
        case '279' : // &&

            // 判断 && 下一个token 是不是新行
            if (is_array($token[$k + 1]) && $line < $token[$k + 1][2]) {
                $data .= PHP_EOL;
                $data .= str_repeat('    ', $indentationNum + 1);
                $data .= $v[1];
                $data .= ' ';
                $andStart = 1;

            } else {
                $data .= ' ' . $v[1] . ' ';

            }
            break;
        case '273' : // ./
            $data .= ' ' . $v[1] . ' ';
            break;
        case '362': //array
            /**
             *
             *    like this: function demo($a, array $b){}
             *
             */
            if ($splitArray && is_array($token[$k + 1]) && $token[$k + 1][0] == 309) {
                $data .= $r . $v[1] . ' '; 
                break;
            }

            /**
             *
             * $a = array(1, 2, 3, 4);
             * 如果array 长度过长 需要使用逗号折行
             *
             */

            if ($arrayStart && $quoteCount == 0) {
                $arrayStart = 0; 
            }
            $data .= $r . $v[1];
            $arrayStart = 1;
            if (!$splitArray) {
                $splitArray = arrayWarp($k, $token);
            }

            break;
        case '315': // 常量

            if ($arrayStart) {
                $data .= $v[1];
                break;
            }
            $data .= $r . $v[1];
            break;
            /**
             * $a->b->c();
             * 或者
             * $a
             *     ->b
             *     ->c();
             */
        case '359': // ->
            if($line < $v[2]) {
                $data .= PHP_EOL . $r . '    ' . $v[1];
            } else {
                $data .= $v[1];
            }
            break;
        case '277': //+=
        case '276': //-=
            $data .= ' ' . $v[1] . ' ';
            break;
        case '263': //OR or
            $data .= ' ' . $v[1] . ' ';
            break;
        case '265': //AND and
            $data .= ' ' . $v[1]. ' ';
            break;
        case '318': //while
            // 如果是以do 开始的 do while. while 不换行 不缩进
            if ($todoStart) {
                $todoStart = 0;
                $data .= $v[1] . ' ';
                break;
            }

            // 如果只是while 循环 需要缩进
            $data .= $r . $v[1] . ' ';
            break;
        case '329': //case
        case '330': //default
            $indentationNum--;
            $r = str_repeat('    ', $indentationNum);
            $data .= $r . $v[1] . ' ';
            break;
        case '281': //===
            $data .= $r . ' ' . $v[1] . ' ';
            break;
            /**
             *
             * throw new Exception
             */
        case '339': //throw
            $data .= $r . $v[1] . ' ';
            break;
            /**
             *
             * switch($a) {
             *     case 1:
             *         break;
             * }
             * 有时候 有些人的写法 不用 break 而是使用return
             */
        case '327': //switch
            $data .= $r . $v[1] . ' ';
            $switchStart = 1;
            $switchStartInd = $indentationNum;
            // 为case-- 做准备
            $indentationNum++;
            break;
            /**
             * 由于以下3个不是function 
             * 所以禁止 include('/path/to');
             *
             */
        case '262': //include
        case '258': // require_once
        case '259': // require
            $data .= $r . $v[1] . ' ';
            break;
        case '380': //::
            $data .= $v[1];
            break;
        default:
            $data .= $r . $v[1];
            break;
    }
    if($line < $v[2]) {
        $theBankToken = $v[1]; 
    }

    $line = $v[2];

}

function display()
{
    echo "\n";
    $class = captureClass();
    echo color("class 命名不规范\n", 'green');
    echo color("-----------------------------\n", 'red');
    foreach ($class as $k => $v) {
        if (!checkName($v, 2)) {
            echo color($v, 'red');
            echo "\n";
        }
    }

    echo "\n";

    echo color("function 命名不规范\n", 'green');
    echo color("-----------------------------\n", 'red');
    $function = captureFunction();
    foreach ($function as $k => $v) {
        if (!checkName($v, 1)) {
            echo color($v, 'red');
            echo "\n";
        }
    }
    echo "\n";
}

function color($str, $color = '')
{
    $colors = array(
        'green' => "\033[32m##\033[39m",
        'red'   => "\033[31m##\033[39m");

    if($color) {
    
        $str = preg_replace('/##/', $str, $colors[$color]);
    }

    return $str;
}

/**
 * 1 function
 * 2 class
 *
 *
 */
function checkName($name, $type = 1)
{

    switch($type) {
        case 1:
            preg_match('/^[A-Z]/', $name, $matches);
            if ($matches) {
                return false;
            }

            preg_match('/[-_]/', $name, $matches);
            if ($matches) {
                return false;
            }
            return true;

            break;
        case 2:
            preg_match('/^[a-z]/', $name, $matches);
            if ($matches) {
                return false;
            }

            preg_match('/[-_]/', $name, $matches);
            if ($matches) {
                return false;
            }
            return true;
            break;
        default:
            return true;
    }
}

$r = fopen($outputFileName, 'w');
fwrite($r, sprintf("%s", $data));
display();
