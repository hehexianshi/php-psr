php-psr
=======
[![Build Status](https://travis-ci.org/hehexianshi/php-psr.svg?branch=master)](https://travis-ci.org/hehexianshi/php-psr)

按照标准生成php文件

---------------------
###BUG
1. if多行参数 最后结尾的) 和 { 需要新起一行
2. @符号bug问题

###修正
1. 当数组长度大于某值时, 并且数组元素后有注释, 导致注释下一行 数据无法正常缩进
2. && 多行时 需要以 && 开始, 并不是 && 的下一个token
3. 当elseif 为单行时 没有使用 {} 包裹
4. php连接符 . 和运算符号 >= 左右没有间距
5. 当sql中出现变量时 变量有缩进问题
