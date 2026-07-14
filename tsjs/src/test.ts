/**
 * CCHQCode Runtime - TypeScript 全功能测试
 */
import { runCCHQ } from './index';

let passed = 0;
let failed = 0;

function test(name: string, fn: () => boolean) {
  try {
    const ok = fn();
    if (ok) {
      console.log(`  ✅ ${name}`);
      passed++;
    } else {
      console.log(`  ❌ ${name} (结果不符合期望)`);
      failed++;
    }
  } catch (e: any) {
    console.log(`  ❌ ${name} (异常: ${e.message})`);
    failed++;
  }
}

function assert(script: string, context: any, expected: any, name: string) {
  test(name, () => {
    const result = runCCHQ(script, context);
    return JSON.stringify(result) === JSON.stringify(expected);
  });
}

console.log('╔══════════════════════════════════════════════╗');
console.log('║   CCHQCode Runtime (TS版) 全功能测试         ║');
console.log('╚══════════════════════════════════════════════╝\n');

// 测试1: 基本函数定义与调用
console.log('▶ 测试1: 基本函数定义与调用');
assert(
  `@Regfunc<>Param:$payload&{@SetCallBackName("H");@ReturnToBot("Hello");}\n@LifeStart(@RunFunc(H,"x"))`,
  {}, "Hello", "返回 Hello");

// 测试2: 变量赋值与运算
console.log('\n▶ 测试2: 变量赋值与运算');
assert(
  `@Regfunc<>Param:$payload&{@SetCallBackName("C");$a=10;$b=20;@ReturnToBot($a+$b);}\n@LifeStart(@RunFunc(C,"x"))`,
  {}, 30, "10+20=30");

// 测试3: if-else
console.log('\n▶ 测试3: if-else 条件');
assert(
  `@Regfunc<>Param:$payload&{@SetCallBackName("J");$a=@GetEventInfo($payload,"age");if($a>=18){@ReturnToBot("a");}else{@ReturnToBot("m");}}\n@LifeStart(@RunFunc(J,$payload))`,
  { payload: { age: 25 } }, "a", "25→a");
assert(
  `@Regfunc<>Param:$payload&{@SetCallBackName("J");$a=@GetEventInfo($payload,"age");if($a>=18){@ReturnToBot("a");}else{@ReturnToBot("m");}}\n@LifeStart(@RunFunc(J,$payload))`,
  { payload: { age: 15 } }, "m", "15→m");

// 测试4: for 循环
console.log('\n▶ 测试4: for 循环');
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("S");$s=0;for(1:10){$s=$s+$i;}@ReturnToBot($s);}\n@LifeStart(@RunFunc(S,"x"))`,
  {}, 55, "1+...+10=55");

// 测试5: while 循环
console.log('\n▶ 测试5: while 循环');
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("W");$c=0;while($c<5){$c=$c+1;}@ReturnToBot($c);}\n@LifeStart(@RunFunc(W,"x"))`,
  {}, 5, "计数=5");

// 测试6: @pick 模式匹配
console.log('\n▶ 测试6: @pick 模式匹配');
assert(
  `@Regfunc<>Param:$val&{@SetCallBackName("P");$r="u";@pick(Param:$val){switch($val){case"b":{$r="mb";break;}default:{}}}` +
  `@ReturnToBot($r);}\n@LifeStart(@RunFunc(P,"b"))`,
  {}, "mb", "匹配b");

// 测试7: 数组字面量
console.log('\n▶ 测试7: 数组字面量');
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("A");@ReturnToBot(["name"=>"ZS","age"=>25,"active"=>true]);}\n@LifeStart(@RunFunc(A,"x"))`,
  {}, { name: "ZS", age: 25, active: true }, "数组含布尔");

// 测试8: 括号表达式与逻辑运算
console.log('\n▶ 测试8: 括号表达式与逻辑');
assert(
  `@Regfunc<>Param:$payload&{@SetCallBackName("L");$a=@GetEventInfo($payload,"age");if(($a>=18)&&($a<65)){@ReturnToBot("ok");}else{@ReturnToBot("no");}}\n@LifeStart(@RunFunc(L,$payload))`,
  { payload: { age: 30 } }, "ok", "30→ok");

// 测试9: @GetEventInfo 嵌套
console.log('\n▶ 测试9: 嵌套数据');
assert(
  `@Regfunc<>Param:$payload&{@SetCallBackName("N");@ReturnToBot(@GetEventInfo($payload,"user.name"));}\n@LifeStart(@RunFunc(N,$payload))`,
  { payload: { user: { name: "Li" } } }, "Li", "user.name");

// 测试10: Param.quantity
console.log('\n▶ 测试10: Param.quantity');
assert(
  `@Regfunc<>Param:any&{@SetCallBackName("Q");@ReturnToBot(@GetEventInfo(Param,quantity));}\n@LifeStart(@RunFunc(Q,"hello"))`,
  {}, 1, "数量=1");

// 测试11: 递归阶乘
console.log('\n▶ 测试11: 递归阶乘');
assert(
  `@Regfunc<>Param:$x&{@SetCallBackName("F");if($x>1){@ReturnToBot($x*@RunFunc(F,$x - 1));}@ReturnToBot(1);}\n@LifeStart(@RunFunc(F,5))`,
  {}, 120, "5!=120");

// 测试12: @EventRestart
console.log('\n▶ 测试12: @EventRestart');
assert(
  `@Regfunc<>Param:$payload&{@SetCallBackName("R");if($payload=="first"){@EventRestart("second");}@ReturnToBot("done");}\n@LifeStart(@RunFunc(R,$payload))`,
  { payload: "first" }, "done", "重启→done");

// 测试13: 综合
console.log('\n▶ 测试13: 综合集成');
assert(
  `@Regfunc<>Param:$payload&{@SetCallBackName("C");$n=@GetEventInfo($payload,"name");$a=@GetEventInfo($payload,"age");` +
  `if($a>=18){$s="a";}else{$s="m";}$sum=0;for(1:$a){$sum=$sum+$i;}` +
  `@ReturnToBot(["name"=>$n,"age"=>$a,"status"=>$s,"sum"=>$sum]);}\n@LifeStart(@RunFunc(C,$payload))`,
  { payload: { name: "WW", age: 5 } },
  { name: "WW", age: 5, status: "m", sum: 15 },
  "综合-m+15");

// 测试14: 多个SetCallBackName
console.log('\n▶ 测试14: 多重SetCallBackName检测');
test("重复应报错", () => {
  try {
    runCCHQ(`@Regfunc<>Param:$x&{@SetCallBackName("A");@SetCallBackName("B");@ReturnToBot(1);}\n@LifeStart(@RunFunc(A,"x"))`, {});
    return false;
  } catch { return true; }
});

// 测试15: 值列表数组
console.log('\n▶ 测试15: 值列表数组');
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("L");@ReturnToBot(["a","b","c"]);}\n@LifeStart(@RunFunc(L,"x"))`,
  {}, { "0": "a", "1": "b", "2": "c" }, "值列表");

// 测试16: EventRestart多次
console.log('\n▶ 测试16: 多次EventRestart');
assert(
  `@Regfunc<>Param:$counter&{@SetCallBackName("CT");if($counter>0){@EventRestart($counter - 1);}@ReturnToBot("zero");}\n@LifeStart(@RunFunc(CT,$payload))`,
  { payload: 2 }, "zero", "3→zero");

// 测试17: 布尔字面量
console.log('\n▶ 测试17: 布尔字面量');
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("BT");@ReturnToBot(true);}\n@LifeStart(@RunFunc(BT,"x"))`,
  {}, true, "true");
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("BF");@ReturnToBot(false);}\n@LifeStart(@RunFunc(BF,"x"))`,
  {}, false, "false");

// 测试18: 非0=true, 0=false
console.log('\n▶ 测试18: 条件布尔转换');
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("C5");if(5){@ReturnToBot(true);}@ReturnToBot(false);}\n@LifeStart(@RunFunc(C5,"x"))`,
  {}, true, "if(5)");
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("C0");if(0){@ReturnToBot(true);}@ReturnToBot(false);}\n@LifeStart(@RunFunc(C0,"x"))`,
  {}, false, "if(0)");

// 测试19: ! 运算符
console.log('\n▶ 测试19: ! 运算符');
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("N0");@ReturnToBot(!0);}\n@LifeStart(@RunFunc(N0,"x"))`,
  {}, true, "!0");
assert(
  `@Regfunc<>Param:$p&{@SetCallBackName("N5");@ReturnToBot(!5);}\n@LifeStart(@RunFunc(N5,"x"))`,
  {}, false, "!5");

console.log('\n╔══════════════════════════════════════════════╗');
console.log(`║   结果: ${passed} 通过, ${failed} 失败              ║`);
console.log('╚══════════════════════════════════════════════╝');
