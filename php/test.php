<?php
// test.php - CCHQCode Runtime 全功能测试
require_once 'CCHQRuntime.php';

echo "╔══════════════════════════════════════════════╗\n";
echo "║   CCHQCode Runtime 全功能测试                ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// 公共辅助函数
function createRuntime($script, $context = []) {
    $runtime = new CCHQRuntime($script, $context);
    $runtime->registerControl('Log', function($msg) {
        echo "  [LOG] $msg\n";
    });
    $runtime->registerControl('Assert', function($condition, $message) {
        if (!$condition) {
            echo "  [失败] $message\n";
        } else {
            echo "  [通过] $message\n";
        }
        return $condition;
    });
    return $runtime;
}

// ==================== 测试1: 基本函数定义与调用 ====================
echo "▶ 测试1: 基本函数定义与调用\n";
$script1 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("HelloFunc");
    @ReturnBack("Hello, CCHQ!");
}
@LifeStart(@RunFunc(HelloFunc, "x"))
CCHQ;
try {
    $r1 = createRuntime($script1);
    $result1 = $r1->run();
    echo "  结果: " . json_encode($result1) . "\n";
    echo "  " . ($result1 === "Hello, CCHQ!" ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试2: 变量赋值与运算 ====================
echo "▶ 测试2: 变量赋值与运算\n";
$script2 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("CalcFunc");
    $a = 10;
    $b = 20;
    $sum = $a + $b;
    $diff = $b - $a;
    $product = $a * $b;
    $quotient = $b / $a;
    $mod = $b % 3;
    @ReturnBack($sum);
}
@LifeStart(@RunFunc(CalcFunc, "x"))
CCHQ;
try {
    $r2 = createRuntime($script2);
    $result2 = $r2->run();
    echo "  10 + 20 = " . json_encode($result2) . "\n";
    echo "  " . ($result2 === 30 ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试3: if-else 条件分支 ====================
echo "▶ 测试3: if-else 条件分支\n";
$script3 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("JudgeFunc");
    $age = @GetEventInfo($payload, "age");
    if($age >= 18) {
        @ReturnBack("adult");
    } else {
        @ReturnBack("minor");
    }
}
@LifeStart(@RunFunc(JudgeFunc, $payload))
CCHQ;
try {
    $r3 = createRuntime($script3, ['payload' => ['age' => 25]]);
    $result3a = $r3->run();
    
    $r3b = createRuntime($script3, ['payload' => ['age' => 15]]);
    $result3b = $r3b->run();
    echo "  age=25 -> " . json_encode($result3a) . " (期望: adult)\n";
    echo "  age=15 -> " . json_encode($result3b) . " (期望: minor)\n";
    echo "  " . ($result3a === "adult" && $result3b === "minor" ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试4: for 循环 ====================
echo "▶ 测试4: for 循环 (1:10)\n";
$script4 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("SumFunc");
    $sum = 0;
    for(1:10) {
        $sum = $sum + $i;
    }
    @ReturnBack($sum);
}
@LifeStart(@RunFunc(SumFunc, "x"))
CCHQ;
try {
    $r4 = createRuntime($script4);
    $result4 = $r4->run();
    echo "  1+2+...+10 = " . json_encode($result4) . " (期望: 55)\n";
    echo "  " . ($result4 === 55 ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试5: while 循环 ====================
echo "▶ 测试5: while 循环\n";
$script5 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("WhileFunc");
    $count = 0;
    while($count < 5) {
        $count = $count + 1;
    }
    @ReturnBack($count);
}
@LifeStart(@RunFunc(WhileFunc, "x"))
CCHQ;
try {
    $r5 = createRuntime($script5);
    $result5 = $r5->run();
    echo "  计数结果: " . json_encode($result5) . " (期望: 5)\n";
    echo "  " . ($result5 === 5 ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试6: @pick 模式匹配 ====================
echo "▶ 测试6: @pick 模式匹配\n";
$script6 = <<<'CCHQ'
@Regfunc<>Param:$val&{
    @SetCallBackName("PickFunc");
    $result = "unknown";
    @pick(Param:$val){
        switch($val){
            case "a":{
                $result = "matched_a";
                break;
            }
            case "b":{
                $result = "matched_b";
                break;
            }
            default:{
                $result = "default";
            }
        }
    }
    @ReturnBack($result);
}
@LifeStart(@RunFunc(PickFunc, "b"))
CCHQ;
try {
    $r6 = createRuntime($script6);
    $result6 = $r6->run();
    echo "  匹配 'b' 结果: " . json_encode($result6) . " (期望: matched_b)\n";
    echo "  " . ($result6 === "matched_b" ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试7: 数组字面量 ====================
echo "▶ 测试7: 数组字面量返回\n";
$script7 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("ArrayFunc");
    @ReturnBack([
        "name" => "张三",
        "age" => 25,
        "active" => true
    ]);
}
@LifeStart(@RunFunc(ArrayFunc, "x"))
CCHQ;
try {
    $r7 = createRuntime($script7);
    $result7 = $r7->run();
    echo "  结果: " . json_encode($result7, JSON_UNESCAPED_UNICODE) . "\n";
    $ok = is_array($result7) && $result7['name'] === '张三' && $result7['age'] === 25 && $result7['active'] === true;
    echo "  " . ($ok ? "✅ 通过 (含布尔值)" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试8: 括号表达式与逻辑运算 ====================
echo "▶ 测试8: 括号表达式与逻辑运算\n";
$script8 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("LogicFunc");
    $age = @GetEventInfo($payload, "age");
    $isAdult = ($age >= 18) && ($age < 65);
    $isSenior = $age >= 65;
    if($isAdult && !$isSenior) {
        @ReturnBack("working_age");
    } else {
        @ReturnBack("other");
    }
}
@LifeStart(@RunFunc(LogicFunc, $payload))
CCHQ;
try {
    $r8 = createRuntime($script8, ['payload' => ['age' => 30]]);
    $result8 = $r8->run();
    echo "  age=30 -> " . json_encode($result8) . " (期望: working_age)\n";
    echo "  " . ($result8 === "working_age" ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试9: @GetEventInfo 嵌套数据访问 ====================
echo "▶ 测试9: @GetEventInfo 嵌套数据访问\n";
$script9 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("NestFunc");
    $name = @GetEventInfo($payload, "user.name");
    $city = @GetEventInfo($payload, "user.address.city");
    @ReturnBack($name);
}
@LifeStart(@RunFunc(NestFunc, $payload))
CCHQ;
try {
    $r9 = createRuntime($script9, ['payload' => [
        'user' => ['name' => '李四', 'address' => ['city' => '北京']]
    ]]);
    $result9 = $r9->run();
    echo "  结果: " . json_encode($result9, JSON_UNESCAPED_UNICODE) . " (期望: 李四)\n";
    echo "  " . ($result9 === '李四' ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试10: @GetEventInfo(Param, quantity) ====================
echo "▶ 测试10: @GetEventInfo(Param, quantity)\n";
$script10 = <<<'CCHQ'
@Regfunc<>Param:any&{
    @SetCallBackName("ParamInfoFunc");
    @ReturnBack(@GetEventInfo(Param, quantity));
}
@LifeStart(@RunFunc(ParamInfoFunc, "hello"))
CCHQ;
try {
    $r10 = createRuntime($script10);
    $result10 = $r10->run();
    echo "  参数数量: " . json_encode($result10) . " (期望: 1)\n";
    echo "  " . ($result10 === 1 ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试11: @RunFunc 递归 ====================
echo "▶ 测试11: @RunFunc 递归 (阶乘)\n";
$script11 = <<<'CCHQ'
@Regfunc<>Param:$x&{
    @SetCallBackName("FactFunc");
    if($x > 1) {
        @ReturnBack($x * @RunFunc(FactFunc, $x - 1));
    }
    @ReturnBack(1);
}
@LifeStart(@RunFunc(FactFunc, 5))
CCHQ;
try {
    $r11 = createRuntime($script11);
    $result11 = $r11->run();
    echo "  5! = " . json_encode($result11) . " (期望: 120)\n";
    echo "  " . ($result11 === 120 ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试12: @EventRestart ====================
echo "▶ 测试12: @EventRestart 重启\n";
$script12 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("RestartFunc");
    if($payload == "first") {
        @EventRestart("second");
    }
    @ReturnBack("done");
}
@LifeStart(@RunFunc(RestartFunc, $payload))
CCHQ;
try {
    $r12 = createRuntime($script12, ['payload' => 'first']);
    $result12 = $r12->run();
    echo "  结果: " . json_encode($result12) . "\n";
    echo "  " . ($result12 === "done" ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试13: 复杂综合测试 ====================
echo "▶ 测试13: 综合集成测试\n";
$script13 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("ComboFunc");
    $name = @GetEventInfo($payload, "name");
    $age = @GetEventInfo($payload, "age");
    $tags = @GetEventInfo($payload, "tags");
    
    if($age >= 18) {
        $status = "adult";
    } else {
        $status = "minor";
    }
    
    $sum = 0;
    for(1:$age) {
        $sum = $sum + $i;
    }
    
    $firstTag = @GetEventInfo($tags, "0");
    
    @ReturnBack([
        "name" => $name,
        "age" => $age,
        "status" => $status,
        "sum" => $sum,
        "firstTag" => $firstTag
    ]);
}
@LifeStart(@RunFunc(ComboFunc, $payload))
CCHQ;
try {
    $r13 = createRuntime($script13, ['payload' => [
        'name' => '王五',
        'age' => 5,
        'tags' => ['developer', 'gamer']
    ]]);
    $result13 = $r13->run();
    echo "  结果: " . json_encode($result13, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    $ok = is_array($result13) 
        && $result13['status'] === 'minor' 
        && $result13['sum'] === 15 
        && $result13['firstTag'] === 'developer';
    echo "  " . ($ok ? "✅ 通过 (minor + 1..5=15 + firstTag)" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试14: 错误检测 ====================
echo "▶ 测试14: 错误检测 - 多个 @SetCallBackName\n";
$script14 = <<<'CCHQ'
@Regfunc<>Param:$x&{
    @SetCallBackName("BadFunc");
    @SetCallBackName("BadFunc2");
    @ReturnBack("err");
}
@LifeStart(@RunFunc(BadFunc, "x"))
CCHQ;
try {
    $r14 = createRuntime($script14);
    $result14 = $r14->run();
    echo "  未检测到错误 ❌\n\n";
} catch (Exception $e) {
    echo "  正确捕获: " . $e->getMessage() . " ✅\n\n";
}

// ==================== 测试15: 值列表格式数组 ====================
echo "▶ 测试15: 值列表格式数组\n";
$script15 = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("ListFunc");
    @ReturnBack(["a", "b", "c"]);
}
@LifeStart(@RunFunc(ListFunc, "x"))
CCHQ;
try {
    $r15 = createRuntime($script15);
    $result15 = $r15->run();
    echo "  结果: " . json_encode($result15) . "\n";
    $ok = is_array($result15) && isset($result15[0]) && $result15[0] === "a" && $result15[2] === "c";
    echo "  " . ($ok ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试16: @EventRestart配合变量参数 ====================
echo "▶ 测试16: @EventRestart + 变量参数\n";
$script16 = <<<'CCHQ'
@Regfunc<>Param:$counter&{
    @SetCallBackName("CounterFunc");
    if($counter > 0) {
        @EventRestart($counter - 1);
    }
    @ReturnBack("zero");
}
@LifeStart(@RunFunc(CounterFunc, $payload))
CCHQ;
try {
    $r16 = createRuntime($script16, ['payload' => 2]);
    $result16 = $r16->run();
    echo "  结果: " . json_encode($result16) . "\n";
    echo "  如果未死循环则通过 ✅\n\n";
} catch (Exception $e) {
    echo "  异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试17: 布尔字面量 ====================
echo "▶ 测试17: 布尔字面量 true/false\n";
$script17a = <<<'CCHQ'
@Regfunc<>Param:$x&{
    @SetCallBackName("BoolTrue");
    @ReturnBack(true);
}
@LifeStart(@RunFunc(BoolTrue, "x"))
CCHQ;
$script17b = <<<'CCHQ'
@Regfunc<>Param:$x&{
    @SetCallBackName("BoolFalse");
    @ReturnBack(false);
}
@LifeStart(@RunFunc(BoolFalse, "x"))
CCHQ;
try {
    $r17a = createRuntime($script17a);
    $result17a = $r17a->run();
    $r17b = createRuntime($script17b);
    $result17b = $r17b->run();
    echo "  true: " . json_encode($result17a) . " | false: " . json_encode($result17b) . "\n";
    echo "  " . ($result17a === true && $result17b === false ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试18: 非0=true, 0=false 条件判断 ====================
echo "▶ 测试18: 非0=true, 0=false 条件\n";
$script18a = <<<'CCHQ'
@Regfunc<>Param:$x&{
    @SetCallBackName("Cond5");
    if(5) { @ReturnBack(true); }
    @ReturnBack(false);
}
@LifeStart(@RunFunc(Cond5, "x"))
CCHQ;
$script18b = <<<'CCHQ'
@Regfunc<>Param:$x&{
    @SetCallBackName("Cond0");
    if(0) { @ReturnBack(true); }
    @ReturnBack(false);
}
@LifeStart(@RunFunc(Cond0, "x"))
CCHQ;
try {
    $r18a = createRuntime($script18a);
    $result18a = $r18a->run();
    $r18b = createRuntime($script18b);
    $result18b = $r18b->run();
    echo "  if(5)=" . json_encode($result18a) . " | if(0)=" . json_encode($result18b) . "\n";
    echo "  " . ($result18a === true && $result18b === false ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

// ==================== 测试19: ! 运算符布尔转换 ====================
echo "▶ 测试19: ! 运算符对数字的布尔转换\n";
$script19a = <<<'CCHQ'
@Regfunc<>Param:$x&{
    @SetCallBackName("Not0");
    @ReturnBack(!0);
}
@LifeStart(@RunFunc(Not0, "x"))
CCHQ;
$script19b = <<<'CCHQ'
@Regfunc<>Param:$x&{
    @SetCallBackName("Not5");
    @ReturnBack(!5);
}
@LifeStart(@RunFunc(Not5, "x"))
CCHQ;
try {
    $r19a = createRuntime($script19a);
    $result19a = $r19a->run();
    $r19b = createRuntime($script19b);
    $result19b = $r19b->run();
    echo "  !0=" . json_encode($result19a) . " | !5=" . json_encode($result19b) . "\n";
    echo "  " . ($result19a === true && $result19b === false ? "✅ 通过" : "❌ 失败") . "\n\n";
} catch (Exception $e) {
    echo "  ❌ 异常: " . $e->getMessage() . "\n\n";
}

echo "╔══════════════════════════════════════════════╗\n";
echo "║   所有测试完成!                                ║\n";
echo "╚══════════════════════════════════════════════╝\n";
