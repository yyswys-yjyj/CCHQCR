<?php
require_once "Lexer.php";
require_once "AST.php";
require_once "Parser.php";
require_once "Environment.php";
require_once "Executor.php";
require_once "Builtins.php";

echo "=== 测试1: GetEventInfo(Param,quantity) ===\n";
$script1 = '@Regfunc<>Param:any&{
    @SetCallBackName("EchoFunc");
    @ReturnBack(@GetEventInfo(Param, quantity));
}
@LifeStart(@RunFunc(EchoFunc, "hello"))';

$env = new Environment([]);
Builtins::register($env);
$env->registerControl("Log", function($msg) { echo "[LOG] $msg\n"; });

$lexer = new Lexer($script1);
$parser = new Parser($lexer->tokenize());
try {
    $ast = $parser->parse();
    $exec = new Executor($env, $ast);
    $result = $exec->run();
    echo "结果: "; var_dump($result);
    echo "期望: int(1)\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

echo "=== 测试2: @EventRestart 重启 ===\n";
$script2 = '@Regfunc<>Param:$payload&{
    @SetCallBackName("RestartDemo");
    @Log("被调用");
    if($payload == "first") {
        @Log("第一次调用, 重启...");
        @EventRestart("second");
    }
    @ReturnBack("ok");
}
@LifeStart(@RunFunc(RestartDemo, "first"))';

$env2 = new Environment([]);
Builtins::register($env2);
$env2->registerControl("Log", function($msg) { echo "[LOG] $msg\n"; });

$lexer2 = new Lexer($script2);
$parser2 = new Parser($lexer2->tokenize());
try {
    $ast2 = $parser2->parse();
    $exec2 = new Executor($env2, $ast2);
    $result2 = $exec2->run();
    echo "结果: "; var_dump($result2);
    echo "期望: string(ok)\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

echo "=== 测试3: @RunFunc 递归 + @GetEventInfo(RunFunc, result) ===\n";
$script3 = '@Regfunc<>Param:$x&{
    @SetCallBackName("FactFunc");
    if($x > 1) {
        @ReturnBack($x * @RunFunc(FactFunc, $x - 1));
    }
    @ReturnBack(1);
}
@LifeStart(@RunFunc(FactFunc, 5))';

$env3 = new Environment([]);
Builtins::register($env3);
$lexer3 = new Lexer($script3);
$parser3 = new Parser($lexer3->tokenize());
try {
    $ast3 = $parser3->parse();
    $exec3 = new Executor($env3, $ast3);
    $result3 = $exec3->run();
    echo "5! 结果: "; var_dump($result3);
    echo "(期望值: int(120))\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

echo "=== 测试4: @pick 表达式形式 ===\n";
$script4 = '@Regfunc<>Param:$data&{
    @SetCallBackName("PickTest");
    @ReturnBack(@GetEventInfo(Param, quantity));
}
@LifeStart(@RunFunc(PickTest, "test"))';

$env4 = new Environment([]);
Builtins::register($env4);
$lexer4 = new Lexer($script4);
$parser4 = new Parser($lexer4->tokenize());
try {
    $ast4 = $parser4->parse();
    $exec4 = new Executor($env4, $ast4);
    $result4 = $exec4->run();
    echo "参数数量: "; var_dump($result4);
    echo "期望: int(1)\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

echo "=== 测试5: @pick 语句形式（switch/case） ===\n";
$script5 = '@Regfunc<>Param:$val&{
    @SetCallBackName("SwitchTest");
    @pick(Param:$val){
        switch($val){
            case "a":{
                @Log("匹配到a");
                break;
            }
            case "b":{
                @Log("匹配到b");
                break;
            }
            default:{
                @Log("匹配到默认值");
            }
        }
    }
    @ReturnBack($val);
}
@LifeStart(@RunFunc(SwitchTest, "b"))';

$env5 = new Environment([]);
Builtins::register($env5);
$env5->registerControl("Log", function($msg) { echo "[LOG] $msg\n"; });
$lexer5 = new Lexer($script5);
$parser5 = new Parser($lexer5->tokenize());
try {
    $ast5 = $parser5->parse();
    $exec5 = new Executor($env5, $ast5);
    $result5 = $exec5->run();
    echo "结果: "; var_dump($result5);
    echo "期望: string(b)\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

echo "=== 测试6: 动态 @Regfunc + 签名 ===\n";
$script6 = '@Regfunc<>Param:$a&{
    @SetCallBackName("MultiFunc");
    @Log("被调用");
    @ReturnBack($a);
}
@LifeStart(@RunFunc(MultiFunc, "hello"))';

$env6 = new Environment([]);
Builtins::register($env6);
$env6->registerControl("Log", function($msg) { echo "[LOG] $msg\n"; });
$lexer6 = new Lexer($script6);
$parser6 = new Parser($lexer6->tokenize());
try {
    $ast6 = $parser6->parse();
    $exec6 = new Executor($env6, $ast6);
    $result6 = $exec6->run();
    echo "结果: "; var_dump($result6);
    echo "期望: string(hello)\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "=== 测试7: 完整示例（数组返回+if/for/while/pick） ===\n";
$script7 = '@Regfunc<>Param:$payload&{
    @SetCallBackName("FullDemo");
    $name = @GetEventInfo($payload, "name");
    $age = @GetEventInfo($payload, "age");
    if($age >= 18) {
        $status = "adult";
    } else {
        $status = "minor";
    }
    $sum = 0;
    for(1:$age) {
        $sum = $sum + $i;
    }
    @ReturnBack([
        "name" => $name,
        "age" => $age,
        "status" => $status,
        "sum" => $sum
    ]);
}
@LifeStart(@RunFunc(FullDemo, $payload))';

$env7 = new Environment(["payload" => ["name" => "测试", "age" => 5]]);
Builtins::register($env7);
$lexer7 = new Lexer($script7);
$parser7 = new Parser($lexer7->tokenize());
try {
    $ast7 = $parser7->parse();
    $exec7 = new Executor($env7, $ast7);
    $result7 = $exec7->run();
    echo "结果: " . json_encode($result7, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "期望: 状态=minor(5<18), sum=1+2+3+4+5=15\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

echo "=== 全部完成 ===\n";
