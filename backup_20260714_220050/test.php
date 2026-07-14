<?php
// test_full.php - 全功能测试
require_once 'CCHQRuntime.php';

// 注册自定义控件
$runtime = new CCHQRuntime('', []);
$runtime->registerControl('Log', function($msg) {
    echo "[LOG] $msg\n";
});
$runtime->registerControl('Assert', function($condition, $message) {
    if (!$condition) {
        echo "[ASSERT FAIL] $message\n";
    } else {
        echo "[ASSERT PASS] $message\n";
    }
    return $condition;
});

// ==================== 测试脚本 ====================
$script = <<<'CCHQ'
// 测试1: 函数定义与调用
@Regfunc<>Param:$payload&{
    @SetCallBackName("EventStart");
    
    // 测试2: 变量赋值
    $name = @GetEventInfo($payload, "name");
    $age = @GetEventInfo($payload, "age");
    $tags = @GetEventInfo($payload, "tags");
    
    // 测试3: if-else
    if ($age >= 18) {
        @Log("成年人: $name");
        $status = "adult";
    } else {
        @Log("未成年人: $name");
        $status = "minor";
    }
    
    // 测试4: for 循环 (1:10)
    $sum = 0;
    for(1:10) {
        $sum = $sum + $i;
    }
    @Log("1到10的和: $sum");
    
    // 测试5: while 循环
    $count = 0;
    while($count < 3) {
        @Log("计数: $count");
        $count = $count + 1;
    }
    
    // 测试6: @pick 模式匹配
    @pick(Param:$status){
        switch($status){
            case "adult":{
                @Log("状态: 成年");
                break;
            }
            case "minor":{
                @Log("状态: 未成年");
                break;
            }
            default:{
                @Log("状态: 未知");
            }
        }
    }
    
    // 测试7: 数组/对象访问（使用 @GetEventInfo）
    $firstTag = @GetEventInfo($tags, "0");
    $secondTag = @GetEventInfo($tags, "1");
    @Log("标签: $firstTag, $secondTag");
    
    // 测试8: 二元运算
    $isAdult = ($age >= 18) && ($age < 65);
    $isSenior = $age >= 65;
    if ($isAdult && !$isSenior) {
        @Log("正值壮年");
    } else {
        @Log("不在壮年范围");
    }
    
    // 测试9: @ReturnToBot 返回值
    @ReturnToBot([
        "name" => $name,
        "age" => $age,
        "status" => $status,
        "sum" => $sum,
        "tags" => $tags
    ]);
}

@LifeStart(@RunFunc(EventStart, $payload))
CCHQ;

// ==================== 执行测试 ====================
$payload = [
    'name' => '张三',
    'age' => 25,
    'tags' => ['developer', 'gamer', 'reader']
];

echo "========== 执行 CCHQ 脚本 ==========\n";
$runtime = new CCHQRuntime($script, ['payload' => $payload]);

// 重新注册 Log 和 Assert
$runtime->registerControl('Log', function($msg) {
    echo "[LOG] $msg\n";
});
$runtime->registerControl('Assert', function($condition, $message) {
    if (!$condition) {
        echo "[ASSERT FAIL] $message\n";
    } else {
        echo "[ASSERT PASS] $message\n";
    }
    return $condition;
});

try {
    $result = $runtime->run();
    echo "\n========== 执行结果 ==========\n";
    echo "返回值: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}