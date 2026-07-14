"""CCHQCode Runtime - Python 全功能测试"""

import json
from . import run_cchq


passed = 0
failed = 0


def test(name, fn):
    global passed, failed
    try:
        ok = fn()
        if ok:
            print(f"  ✅ {name}")
            passed += 1
        else:
            print(f"  ❌ {name} (结果不符合期望)")
            failed += 1
    except Exception as e:
        print(f"  ❌ {name} (异常: {e})")
        failed += 1


def assert_eq(script, context, expected, name):
    def fn():
        result = run_cchq(script, context)
        return json.dumps(result, sort_keys=True, ensure_ascii=False) == \
               json.dumps(expected, sort_keys=True, ensure_ascii=False)
    test(name, fn)


print("╔══════════════════════════════════════════════╗")
print("║   CCHQCode Runtime (Python版) 全功能测试     ║")
print("╚══════════════════════════════════════════════╝\n")

# 测试1: 基本函数定义与调用
print("▶ 测试1: 基本函数定义与调用")
assert_eq(
    '@Regfunc<>Param:$x&{@SetCallBackName("H");@ReturnToBot("Hello");}\n@LifeStart(@RunFunc(H,"x"))',
    {}, "Hello", "返回 Hello")

# 测试2: 变量赋值与运算
print("\n▶ 测试2: 变量赋值与运算")
assert_eq(
    '@Regfunc<>Param:$x&{@SetCallBackName("C");$a=10;$b=20;@ReturnToBot($a+$b);}\n@LifeStart(@RunFunc(C,"x"))',
    {}, 30, "10+20=30")

# 测试3: if-else
print("\n▶ 测试3: if-else 条件")
assert_eq(
    '@Regfunc<>Param:$payload&{@SetCallBackName("J");$a=@GetEventInfo($payload,"age");if($a>=18){@ReturnToBot("a");}else{@ReturnToBot("m");}}\n@LifeStart(@RunFunc(J,$payload))',
    {"payload": {"age": 25}}, "a", "25→a")
assert_eq(
    '@Regfunc<>Param:$payload&{@SetCallBackName("J");$a=@GetEventInfo($payload,"age");if($a>=18){@ReturnToBot("a");}else{@ReturnToBot("m");}}\n@LifeStart(@RunFunc(J,$payload))',
    {"payload": {"age": 15}}, "m", "15→m")

# 测试4: for 循环
print("\n▶ 测试4: for 循环")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("S");$s=0;for(1:10){$s=$s+$i;}@ReturnToBot($s);}\n@LifeStart(@RunFunc(S,"x"))',
    {}, 55, "1+...+10=55")

# 测试5: while 循环
print("\n▶ 测试5: while 循环")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("W");$c=0;while($c<5){$c=$c+1;}@ReturnToBot($c);}\n@LifeStart(@RunFunc(W,"x"))',
    {}, 5, "计数=5")

# 测试6: @pick
print("\n▶ 测试6: @pick 模式匹配")
assert_eq(
    '@Regfunc<>Param:$val&{@SetCallBackName("P");$r="u";@pick(Param:$val){switch($val){case"b":{$r="mb";break;}default:{}}}@ReturnToBot($r);}\n@LifeStart(@RunFunc(P,"b"))',
    {}, "mb", "匹配b")

# 测试7: 数组字面量
print("\n▶ 测试7: 数组字面量")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("A");@ReturnToBot(["name"=>"ZS","age"=>25,"active"=>true]);}\n@LifeStart(@RunFunc(A,"x"))',
    {}, {"name": "ZS", "age": 25, "active": True}, "数组含布尔")

# 测试8: 括号表达式与逻辑
print("\n▶ 测试8: 括号表达式与逻辑")
assert_eq(
    '@Regfunc<>Param:$payload&{@SetCallBackName("L");$a=@GetEventInfo($payload,"age");if(($a>=18)&&($a<65)){@ReturnToBot("ok");}else{@ReturnToBot("no");}}\n@LifeStart(@RunFunc(L,$payload))',
    {"payload": {"age": 30}}, "ok", "30→ok")

# 测试9: 嵌套数据
print("\n▶ 测试9: 嵌套数据")
assert_eq(
    '@Regfunc<>Param:$payload&{@SetCallBackName("N");@ReturnToBot(@GetEventInfo($payload,"user.name"));}\n@LifeStart(@RunFunc(N,$payload))',
    {"payload": {"user": {"name": "Li"}}}, "Li", "user.name")

# 测试10: Param.quantity
print("\n▶ 测试10: Param.quantity")
assert_eq(
    '@Regfunc<>Param:any&{@SetCallBackName("Q");@ReturnToBot(@GetEventInfo(Param,quantity));}\n@LifeStart(@RunFunc(Q,"hello"))',
    {}, 1, "数量=1")

# 测试11: 递归阶乘
print("\n▶ 测试11: 递归阶乘")
assert_eq(
    '@Regfunc<>Param:$x&{@SetCallBackName("F");if($x>1){@ReturnToBot($x*@RunFunc(F,$x - 1));}@ReturnToBot(1);}\n@LifeStart(@RunFunc(F,5))',
    {}, 120, "5!=120")

# 测试12: @EventRestart
print("\n▶ 测试12: @EventRestart")
assert_eq(
    '@Regfunc<>Param:$payload&{@SetCallBackName("R");if($payload=="first"){@EventRestart("second");}@ReturnToBot("done");}\n@LifeStart(@RunFunc(R,$payload))',
    {"payload": "first"}, "done", "重启→done")

# 测试13: 综合集成
print("\n▶ 测试13: 综合集成")
assert_eq(
    '@Regfunc<>Param:$payload&{@SetCallBackName("C");$n=@GetEventInfo($payload,"name");$a=@GetEventInfo($payload,"age");'
    'if($a>=18){$s="a";}else{$s="m";}$sum=0;for(1:$a){$sum=$sum+$i;}'
    '@ReturnToBot(["name"=>$n,"age"=>$a,"status"=>$s,"sum"=>$sum]);}\n@LifeStart(@RunFunc(C,$payload))',
    {"payload": {"name": "WW", "age": 5}},
    {"name": "WW", "age": 5, "status": "m", "sum": 15},
    "综合-m+15")

# 测试14: 多重SetCallBackName
print("\n▶ 测试14: 多重SetCallBackName检测")


def _test_dup():
    try:
        run_cchq('@Regfunc<>Param:$x&{@SetCallBackName("A");@SetCallBackName("B");@ReturnToBot(1);}\n@LifeStart(@RunFunc(A,"x"))', {})
        return False
    except SyntaxError:
        return True


test("重复应报错", _test_dup)


# 测试15: 值列表数组
print("\n▶ 测试15: 值列表数组")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("L");@ReturnToBot(["a","b","c"]);}\n@LifeStart(@RunFunc(L,"x"))',
    {}, {"0": "a", "1": "b", "2": "c"}, "值列表")

# 测试16: 多次EventRestart
print("\n▶ 测试16: 多次EventRestart")
assert_eq(
    '@Regfunc<>Param:$counter&{@SetCallBackName("CT");if($counter>0){@EventRestart($counter - 1);}@ReturnToBot("zero");}\n@LifeStart(@RunFunc(CT,$payload))',
    {"payload": 2}, "zero", "3→zero")

# 测试17: 布尔字面量
print("\n▶ 测试17: 布尔字面量")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("BT");@ReturnToBot(true);}\n@LifeStart(@RunFunc(BT,"x"))',
    {}, True, "true")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("BF");@ReturnToBot(false);}\n@LifeStart(@RunFunc(BF,"x"))',
    {}, False, "false")

# 测试18: 条件布尔转换
print("\n▶ 测试18: 条件布尔转换")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("C5");if(5){@ReturnToBot(true);}@ReturnToBot(false);}\n@LifeStart(@RunFunc(C5,"x"))',
    {}, True, "if(5)")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("C0");if(0){@ReturnToBot(true);}@ReturnToBot(false);}\n@LifeStart(@RunFunc(C0,"x"))',
    {}, False, "if(0)")

# 测试19: ! 运算符
print("\n▶ 测试19: ! 运算符")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("N0");@ReturnToBot(!0);}\n@LifeStart(@RunFunc(N0,"x"))',
    {}, True, "!0")
assert_eq(
    '@Regfunc<>Param:$p&{@SetCallBackName("N5");@ReturnToBot(!5);}\n@LifeStart(@RunFunc(N5,"x"))',
    {}, False, "!5")

print(f'\n╔══════════════════════════════════════════════╗')
print(f'║   结果: {passed} 通过, {failed} 失败              ║')
print(f'╚══════════════════════════════════════════════╝')
