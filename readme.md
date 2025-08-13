```
//$name:  加载文件的命名空间

//tsx 注册自动加载函数
spl_autoload_register(function ($name){
    $name = str_replace('\\',DIRECTORY_SEPARATOR, $name);
    $namespace = explode('/', $name);
    switch ($namespace[0]) {
        case 'think':
           $name = 'Xphp'.DIRECTORY_SEPARATOR.'Kcloze'.DIRECTORY_SEPARATOR.$name;
            break;
        case 'Kcloze':
           $name = 'Xphp'.DIRECTORY_SEPARATOR.$name;
            break;
        default:
            $name = $name;
            break;
    }
    
    //$name = str_replace('\\',DIRECTORY_SEPARATOR, $name);
    //判断命名空间的所需要加载的类
    
  
    $file = SWOOLE_XYH_PATH.DIRECTORY_SEPARATOR.$name.'.php';
    if(is_file($file)){
        @include_once $file;
    }else{
       // echo '--',$file.PHP_EOL;
    } 
});
```
