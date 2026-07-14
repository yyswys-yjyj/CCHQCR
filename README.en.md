# CCH Quick Code Runtime (CCHQCR)

> [简体中文](README.md) · **English**

**CCHQCode** is a lightweight embedded scripting language designed for event-driven business logic scenarios.  
CCHQCode Runtime provides **PHP**, **TypeScript/JavaScript**, and **Python** implementations — drop it directly into your project.

---

## Quick Start

```cchq
@Regfunc<>Param:$payload&{
    @SetCallBackName("Main");
    $name = @GetEventInfo($payload, "name");
    @ReturnToBot("Hello, " + $name);
}

@LifeStart(@RunFunc(Main, $payload))
```

Input `{ "name": "World" }`, returns `"Hello, World"`.

---

## Language Syntax

### Function Definition

```cchq
@Regfunc<>Param:$paramName&{
    @SetCallBackName("FuncName");
    // body
}
```

Two parameter formats:

**Named variables:**
```cchq
@Regfunc<>Param:$a,$b&{ ... }
```

**Type declaration (whole value as Param):**
```cchq
@Regfunc<>Param:any&{ ... }
@Regfunc<>Param:bool&{ ... }
```

- `&` terminates the parameter list
- `@SetCallBackName` registers the function name — **only one per function**

#### Overloading

Functions are overloaded by argument count:

```cchq
@Regfunc<>Param:$a&{ @SetCallBackName("F"); /* 1 arg */ }
@Regfunc<bool>Param:$a,$b&{ @SetCallBackName("F"); /* 2 args */ }
```

### Lifecycle Entry

```cchq
@LifeStart(@RunFunc(FuncName, args...))
@LifeStart(@RunFunc(Main, $payload))
```

### Built-in Controls

| Control | Description |
|---------|-------------|
| `@SetCallBackName("name")` | Register function name |
| `@GetEventInfo(data, path)` | Read nested data by dot-path |
| `@ReturnToBot(value)` | Return value |
| `@Log(message)` | Print log |
| `@RunFunc(name, ...args)` | Call function (recursive) |

**Special @GetEventInfo usages:**

```cchq
@GetEventInfo(Param, quantity)   // Get argument count
@GetEventInfo(RunFunc, result)   // Get last @RunFunc result
```

### Variables & Assignment

```cchq
$name = "John";
$age = 25;
$sum = $a + $b;
$flag = true;
```

**Scope:** Function-level. Variables assigned inside `if`/`for`/`while` blocks are visible outside.  
Loop variables (`$i`) are only valid inside the loop body.

### Data Types

| Type | Example |
|------|---------|
| String | `"hello"`, `'world'` |
| Integer | `42`, `-1`, `0` |
| Float | `3.14` |
| Boolean | `true`, `false` |
| Array | `["a","b"]`, `["key"=>"value"]` |

**Boolean rule:** Non-zero numbers are `true`, `0` is `false`.

### Operators

| Category | Operators |
|----------|-----------|
| Arithmetic | `+`, `-`, `*`, `/`, `%` |
| Comparison | `==`, `!=`, `<`, `>`, `<=`, `>=` |
| Logical | `&&`, `\|\|`, `!` |
| Unary | `-` (negate), `!` (not) |
| Grouping | `(expr)` |

### Conditionals

```cchq
if(condition) { ... } else { ... }
if(cond) { ... } else if(cond) { ... } else { ... }
```

### Loops

**for:**
```cchq
for(1:10) { $sum = $sum + $i; }     // 1+2+...+10
for(1:$count) { ... }               // variable end
for(1:10:2) { ... }                 // step = 2
```

**while:**
```cchq
while(cond) { break; continue; }
```

### Pattern Matching @pick

**Statement form (switch/case):**
```cchq
@pick(Param:$var){
    switch($var){
        case "a":{ ... break; }
        case "b":{ ... break; }
        default:{ ... }
    }
}
```

**Expression form (extract value):**
```cchq
@EventRestart(@pick(Param:$a));
```

### Array Literals

```cchq
@ReturnToBot(["name"=>"Alice", "age"=>25, "active"=>true]);
@ReturnToBot(["a", "b", "c"]);    // auto-indexed
@ReturnToBot([]);                 // empty
```

### Recursion

```cchq
@Regfunc<>Param:$x&{
    @SetCallBackName("Fact");
    if($x > 1) {
        @ReturnToBot($x * @RunFunc(Fact, $x - 1));
    }
    @ReturnToBot(1);
}
@LifeStart(@RunFunc(Fact, 5))   // 120
```

**No-arg recursion** automatically inherits current `Param`:
```cchq
@RunFunc(AFunc);  // uses current Param as arguments
```

### Event Restart

Restart the lifecycle with a new payload:

```cchq
@Regfunc<>Param:$payload&{
    @SetCallBackName("Handler");
    if($payload == "first") {
        @EventRestart("second");
    }
    @ReturnToBot("done");
}
@LifeStart(@RunFunc(Handler, $payload))
```

### Comments

```cchq
// single line only
```

---

## PHP Integration

```php
require_once '/path/to/php/CCHQRuntime.php';

$runtime = new CCHQRuntime($script, ['payload' => ['name' => 'World']]);
$result = $runtime->run();
```

### Custom Controls

```php
$runtime->registerControl('Double', function($x) { return $x * 2; });
```

---

## TypeScript / JavaScript Integration

### Node.js (CommonJS)

```javascript
const { runCCHQ, createRuntime } = require('./tsjs/dist/index');

const result = runCCHQ(`...script...`, { payload: "World" });
```

### ES Module / TypeScript

```typescript
import { runCCHQ, createRuntime } from './tsjs/src/index';

const rt = createRuntime({ payload: "test" });
rt.registerControl('Double', (x) => x * 2);
const r = rt.execute(`...script...`);
```

### API

```typescript
function runCCHQ(script: string, context?: any): any;
function createRuntime(context?: any): {
  execute(script: string): any;
  registerControl(name: string, callable: Function): void;
};
```

---

## Python Integration

```python
from python import run_cchq, create_runtime

result = run_cchq("""...script...""", {"payload": "World"})

rt = create_runtime({"payload": "test"})
rt.register_control("Double", lambda x: x * 2)
r1 = rt.execute("...")
```

### API

```python
def run_cchq(script: str, context: dict = {}) -> Any
def create_runtime(context: dict = {}) -> Runtime:
    # .execute(script) -> Any
    # .register_control(name, callable)
    # .get_environment() -> Environment
```

---

## Full Example

```cchq
@Regfunc<>Param:$payload&{
    @SetCallBackName("Process");

    $name = @GetEventInfo($payload, "user.name");
    $age  = @GetEventInfo($payload, "user.age");

    if($age >= 18) { $status = "adult"; }
    else           { $status = "minor"; }

    $sum = 0;
    for(1:$age) { $sum = $sum + $i; }

    $count = 0;
    while($count < 3) { @Log("count: " + $count); $count = $count + 1; }

    @pick(Param:$status){
        switch($status){
            case "adult":{ @Log("adult"); break; }
            case "minor":{ @Log("minor"); break; }
        }
    }

    @ReturnToBot(["name"=>$name, "age"=>$age, "status"=>$status, "sum"=>$sum]);
}
@LifeStart(@RunFunc(Process, $payload))
```

Input:
```json
{ "payload": { "user": { "name": "Alice", "age": 5 } } }
```

Output:
```json
{ "name": "Alice", "age": 5, "status": "minor", "sum": 15 }
```

---

## Project Structure

```
CCHQCR/
├── README.md                  本文档（简体中文）
├── README.en.md               本文档（English）
├── php/                       PHP runtime
│   ├── Lexer.php / Parser.php / AST.php
│   ├── Environment.php / Executor.php
│   ├── Builtins.php / CCHQRuntime.php
│   └── test.php               19 tests
├── tsjs/                      TypeScript/JS runtime
│   ├── src/                   source
│   │   ├── index.ts / lexer.ts / parser.ts
│   │   ├── ast.ts / environment.ts / executor.ts
│   │   ├── builtins.ts / types.ts
│   │   └── test.ts            23 tests
│   ├── dist/                  compiled JS (zero deps)
│   └── tsconfig.json
└── python/                    Python runtime
    ├── __init__.py / lexer.py / parser.py
    ├── ast.py / environment.py / executor.py
    ├── builtins.py / types.py
    └── test.py                23 tests
```
