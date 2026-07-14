# CCH Quick Code Runtime(CCHQCR)

**CCHQCode** 是一种轻量级、嵌入式脚本语言，专为事件驱动的业务逻辑场景设计。  
CCHQCode Runtime 提供了 PHP 和 TypeScript/JavaScript 双版本实现，可直接嵌入到你的项目中。

---

## 目录

- [快速开始](#快速开始)
- [语言语法](#语言语法)
  - [函数定义](#函数定义)
  - [生命周期入口](#生命周期入口)
  - [内置函数（控件）](#内置函数控件)
  - [变量与赋值](#变量与赋值)
  - [数据类型](#数据类型)
  - [运算符](#运算符)
  - [条件分支](#条件分支)
  - [循环](#循环)
  - [模式匹配 @pick](#模式匹配-pick)
  - [数组字面量](#数组字面量)
  - [函数调用与递归](#函数调用与递归)
  - [事件重启 @EventRestart](#事件重启-eventrestart)
  - [注释](#注释)
- [PHP 接入](#php-接入)
- [TypeScript / JavaScript 接入](#typescript--javascript-接入)
- [完整示例](#完整示例)

---

## 快速开始

```cchq
@Regfunc<>Param:$payload&{
    @SetCallBackName("Main");
    $name = @GetEventInfo($payload, "name");
    @ReturnToBot("你好, " + $name);
}

@LifeStart(@RunFunc(Main, $payload))
```

传入 `{ "name": "世界" }`，返回 `"你好, 世界"`。

---

## 语言语法

### 函数定义

使用 `@Regfunc` 定义函数：

```cchq
@Regfunc<>Param:$变量名&{
    @SetCallBackName("函数名");
    // 函数体
}
```

- `<>` 中声明函数的类型签名（可空）
- `Param:` 后跟参数声明，支持两种格式：

**变量列表格式：**
```cchq
@Regfunc<>Param:$a,$b&{ ... }
```

**类型声明格式（参数作为整体传入）：**
```cchq
@Regfunc<>Param:any&{ ... }
@Regfunc<>Param:bool&{ ... }
```

- `&` 为参数终止符
- `@SetCallBackName` 为函数注册名称，**一个函数只能有一个**

#### 函数重载

按参数数量区分重载：

```cchq
@Regfunc<>Param:$a&{
    @SetCallBackName("Func");
    // 1 个参数版本
}

@Regfunc<bool>Param:$a,$b&{
    @SetCallBackName("Func");
    // 2 个参数版本（重载）
}
```

---

### 生命周期入口

`@LifeStart` 定义程序入口：

```cchq
@LifeStart(@RunFunc(函数名, 参数...))
@LifeStart(@RunFunc(函数名, $payload))
```

---

### 内置函数（控件）

| 控件 | 说明 |
|------|------|
| `@SetCallBackName("name")` | 注册函数名称 |
| `@GetEventInfo(data, path)` | 从 data 中按点路径读取数据 |
| `@ReturnToBot(value)` | 返回值 |
| `@Log(message)` | 输出日志 |
| `@RunFunc(name, ...args)` | 调用函数（支持递归） |

**@GetEventInfo 特殊用法：**

```cchq
@GetEventInfo(Param, quantity)   // 获取当前函数参数数量（返回数字）
@GetEventInfo(RunFunc, result)   // 获取最近一次 @RunFunc 的结果
```

---

### 变量与赋值

变量以 `$` 开头，使用 `=` 赋值：

```cchq
$name = "张三";
$age = 25;
$result = $a + $b;
$flag = true;
```

变量作用域：**函数级作用域**，if/for/while 块内赋值的变量外部可见。  
for/while 的循环变量（`$i`）仅在循环体内有效。

---

### 数据类型

| 类型 | 示例 |
|------|------|
| 字符串 | `"hello"`, `'world'` |
| 整数 | `42`, `-1`, `0` |
| 浮点数 | `3.14` |
| 布尔 | `true`, `false` |
| 数组 | `["a", "b"]`, `["key" => "value"]` |
| null | 通过函数返回 |

**布尔规则：** 非 `0` 数字为 `true`，`0` 为 `false`。

---

### 运算符

| 类别 | 运算符 |
|------|--------|
| 算术 | `+`, `-`, `*`, `/`, `%` |
| 比较 | `==`, `!=`, `<`, `>`, `<=`, `>=` |
| 逻辑 | `&&`, `\|\|`, `!` |
| 一元 | `-`（负号）, `!`（非） |
| 括号 | `(expr)` 改变优先级 |

---

### 条件分支

```cchq
if(条件) {
    // then
} else {
    // else
}
```

```cchq
if(条件) {
    // then
} else if(条件) {
    // else if
} else {
    // else
}
```

---

### 循环

**for 循环：**

```cchq
for(起始值:结束值) {
    // $i 为循环变量，从 起始值 到 结束值
}

for(1:10) { $sum = $sum + $i; }        // 1+2+...+10
for(1:$count) { ... }                  // 使用变量作为结束值
for(1:10:2) { ... }                    // 步进为 2
```

**while 循环：**

```cchq
while(条件) {
    // 循环体
    break;      // 跳出循环
    continue;   // 继续下一次循环
}
```

---

### 模式匹配 @pick

**语句形式（switch/case）：**

```cchq
@pick(Param:$变量名){
    switch($变量名){
        case "值1":{
            // 匹配值1
            break;
        }
        case "值2":{
            // 匹配值2
            break;
        }
        default:{
            // 默认分支
        }
    }
}
```

**表达式形式（提取值）：**

```cchq
@EventRestart(@pick(Param:$a));   // 从 Param 中提取 $a 的值
```

---

### 数组字面量

**键值对格式：**

```cchq
@ReturnToBot([
    "name" => "张三",
    "age" => 25,
    "active" => true
]);
```

**值列表格式（自动分配数字索引）：**

```cchq
@ReturnToBot(["a", "b", "c"]);
// 结果: { "0": "a", "1": "b", "2": "c" }
```

**空数组：**

```cchq
@ReturnToBot([]);
```

---

### 函数调用与递归

```cchq
// 直接调用
$result = @RunFunc(函数名, 参数1, 参数2);

// 递归（阶乘示例）
@Regfunc<>Param:$x&{
    @SetCallBackName("Fact");
    if($x > 1) {
        @ReturnToBot($x * @RunFunc(Fact, $x - 1));
    }
    @ReturnToBot(1);
}

@LifeStart(@RunFunc(Fact, 5))   // 结果: 120
```

**无参递归继承当前参数：**

```cchq
@RunFunc(AFunc);   // 自动使用当前函数的 Param 作为参数
```

---

### 事件重启 @EventRestart

重启整个生命周期，使用新的 payload 重新执行 `@LifeStart` 表达式：

```cchq
@Regfunc<>Param:$payload&{
    @SetCallBackName("Handler");
    if($payload == "first") {
        @EventRestart("second");   // 用 "second" 重启
    }
    @ReturnToBot("done");
}

@LifeStart(@RunFunc(Handler, $payload))
// 传入 "first"，最终返回 "done"
```

---

### 注释

```cchq
// 单行注释（仅支持 // 格式）
```

---

## PHP 接入

### 直接运行

```php
<?php
require_once '/path/to/CCHQCR/php/CCHQRuntime.php';

$script = <<<'CCHQ'
@Regfunc<>Param:$payload&{
    @SetCallBackName("Main");
    $name = @GetEventInfo($payload, "name");
    @ReturnToBot("Hello, " + $name);
}
@LifeStart(@RunFunc(Main, $payload))
CCHQ;

$runtime = new CCHQRuntime($script, ['payload' => ['name' => 'World']]);
$result = $runtime->run();

echo $result; // "Hello, World"
```

### 注册自定义控件

```php
<?php
$runtime = new CCHQRuntime($script, $context);
$runtime->registerControl('MyFunc', function($arg1, $arg2) {
    return $arg1 + $arg2;
});
$result = $runtime->run();
```

---

## TypeScript / JavaScript 接入

### 安装

无需安装任何 npm 包，直接引入编译后的文件即可。

### Node.js (CommonJS)

```javascript
const { runCCHQ, createRuntime } = require('./tsjs/dist/index');

// 方式一：一次性执行
const result = runCCHQ(`
  @Regfunc<>Param:$payload&{
    @SetCallBackName("Main");
    @ReturnToBot("Hello, " + $payload);
  }
  @LifeStart(@RunFunc(Main, $payload))
`, { payload: "World" });

console.log(result); // "Hello, World"


// 方式二：创建可复用的 Runtime 实例
const rt = createRuntime({ payload: "test" });
rt.registerControl('Double', (x) => x * 2);

const r1 = rt.execute(`...`);
const r2 = rt.execute(`...`); // 共享环境
```

### ES Module / TypeScript

```typescript
import { runCCHQ, createRuntime } from './tsjs/src/index';

// 直接传入源码字符串，零依赖执行
const result = runCCHQ(`
  @Regfunc<>Param:$x&{@SetCallBackName("F");if($x>1){@ReturnToBot($x*@RunFunc(F,$x-1));}@ReturnToBot(1);}
  @LifeStart(@RunFunc(F,5))
`, {});
console.log(result); // 120
```

### API 参考

```typescript
/**
 * 执行 CCHQ 脚本，返回执行结果
 * @param script  源码字符串
 * @param context 上下文数据（如 { payload: ... }）
 */
function runCCHQ(script: string, context?: any): any;

/**
 * 创建可复用的 Runtime 实例
 */
function createRuntime(context?: any): {
  execute(script: string): any;
  registerControl(name: string, callable: Function): void;
  getEnvironment(): Environment;
};
```

---

## 完整示例

### 综合脚本

```cchq
@Regfunc<>Param:$payload&{
    @SetCallBackName("Process");

    // 读取数据
    $name = @GetEventInfo($payload, "user.name");
    $age  = @GetEventInfo($payload, "user.age");

    // 条件判断
    if($age >= 18) {
        $status = "adult";
    } else {
        $status = "minor";
    }

    // for 循环求和
    $sum = 0;
    for(1:$age) {
        $sum = $sum + $i;
    }

    // while 循环
    $count = 0;
    while($count < 3) {
        @Log("count: " + $count);
        $count = $count + 1;
    }

    // 模式匹配
    @pick(Param:$status){
        switch($status){
            case "adult":{
                @Log("成年人");
                break;
            }
            case "minor":{
                @Log("未成年人");
                break;
            }
        }
    }

    // 返回数组
    @ReturnToBot([
        "name"   => $name,
        "age"    => $age,
        "status" => $status,
        "sum"    => $sum
    ]);
}

@LifeStart(@RunFunc(Process, $payload))
```

传入：
```json
{
  "payload": {
    "user": { "name": "张三", "age": 5 }
  }
}
```

返回：
```json
{
  "name": "张三",
  "age": 5,
  "status": "minor",
  "sum": 15
}
```

---

## 项目结构

```
CCHQCR/
├── README.md              ← 本文档
├── php/                   ← PHP 运行时
│   ├── Lexer.php          词法分析
│   ├── Parser.php         语法分析
│   ├── AST.php            AST 节点
│   ├── Environment.php    运行环境
│   ├── Executor.php       执行器
│   ├── Builtins.php       内置函数
│   ├── CCHQRuntime.php    入口类
│   └── test.php           19 项测试
└── tsjs/                  ← TypeScript/JS 运行时
    ├── src/               源码
    │   ├── index.ts        入口
    │   ├── types.ts
    │   ├── lexer.ts
    │   ├── parser.ts
    │   ├── ast.ts
    │   ├── environment.ts
    │   ├── builtins.ts
    │   ├── executor.ts
    │   └── test.ts         23 项测试
    ├── dist/              编译产物（零依赖）
    │   └── *.js
    └── tsconfig.json
```
