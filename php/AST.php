<?php
// AST.php - 抽象语法树节点定义

abstract class ASTNode {
    abstract public function execute($env);
}

// 程序根节点
class ProgramNode extends ASTNode {
    public $functions = [];
    public $lifeStartExpr = null;
    public function execute($env) {
        // 注册所有函数
        foreach ($this->functions as $func) {
            $env->registerFunction($func->name, $func, $func->paramCount);
        }
        // 执行 LifeStart 表达式（需要求值并调用）
        if ($this->lifeStartExpr) {
            return $this->lifeStartExpr->execute($env);
        }
        return null;
    }
}

// 函数定义节点
class FunctionDefNode extends ASTNode {
    public $name;
    public $signature;   // 类型签名（如 'bool', 'string,int' 或 ''）
    public $params;      // 参数名数组
    public $paramCount;
    public $body;        // BlockNode
    public $returnType;  // 可选，暂忽略
    public function execute($env) {
        // 注册自己（但通常在 ProgramNode 中统一注册）
        $env->registerFunction($this->name, $this, $this->paramCount);
        return null;
    }
}

// 块节点（花括号内语句列表）
class BlockNode extends ASTNode {
    public $statements = [];
    public function execute($env) {
        $returnVal = null;
        foreach ($this->statements as $stmt) {
            $result = $stmt->execute($env);
            // 如果遇到 return 或 break/continue，向上传递
            if ($result instanceof ControlSignal) {
                return $result;
            }
            $returnVal = $result; // 保留最后一个语句的值（非控制信号）
        }
        return $returnVal;
    }
}

// 控制信号（break, continue, return）
class ControlSignal {
    public $type; // 'break', 'continue', 'return'
    public $value;
    public function __construct($type, $value = null) {
        $this->type = $type;
        $this->value = $value;
    }
}

// 表达式节点（基础）
abstract class ExpressionNode extends ASTNode {
    // 由具体子类实现
}

// 字面量节点
class LiteralNode extends ExpressionNode {
    public $value;
    public function __construct($value) { $this->value = $value; }
    public function execute($env) { return $this->value; }
}

class UnaryOpNode extends ExpressionNode {
    public $operator;
    public $operand;

    public function __construct($operator, $operand) {
        $this->operator = $operator;
        $this->operand = $operand;
    }

    public function execute($env) {
        $operand = $this->operand->execute($env);
        switch ($this->operator) {
            case '!': return !$operand;
            case '-': return -$operand;
            default: throw new Exception("Unknown unary operator: " . $this->operator);
        }
    }
}

// 变量节点
class VariableNode extends ExpressionNode {
    public $name;
    public function __construct($name) { $this->name = $name; }
    public function execute($env) {
        return $env->getVariable($this->name);
    }
}

// 赋值节点（变量 = 表达式）
class AssignNode extends ExpressionNode {
    public $varName;
    public $expr;
    public function __construct($varName, $expr) {
        $this->varName = $varName;
        $this->expr = $expr;
    }
    public function execute($env) {
        $value = $this->expr->execute($env);
        $env->setVariable($this->varName, $value);
        return $value;
    }
}

// 调用节点（控件调用：@Name(...) 或函数调用?）
class CallNode extends ExpressionNode {
    public $name;
    public $args;
    public function __construct($name, $args) {
        $this->name = $name;
        $this->args = $args;
    }
    public function execute($env) {
        $argValues = [];
        foreach ($this->args as $arg) {
            $argValues[] = $arg->execute($env);
        }
        // 首先尝试作为控件
        if ($env->hasControl($this->name)) {
            return $env->callControl($this->name, $argValues);
        }
        // 否则尝试作为函数
        if ($env->hasFunction($this->name, count($argValues))) {
            return $env->callFunction($this->name, $argValues);
        }
        throw new Exception("Undefined callable: {$this->name}");
    }
}

// If 节点
class IfNode extends ASTNode {
    public $condition;
    public $thenBranch;
    public $elseBranch; // 可能为 null
    public function execute($env) {
        $cond = $this->condition->execute($env);
        if ($cond) {
            return $this->thenBranch->execute($env);
        } elseif ($this->elseBranch) {
            return $this->elseBranch->execute($env);
        }
        return null;
    }
}

// For 循环节点 (start:end:step)
class ForNode extends ASTNode {
    public $start;
    public $end;
    public $step; // 可选
    public $body;
    public $loopVar = 'i'; // 默认变量名
    public function execute($env) {
        $start = $this->start->execute($env);
        $end = $this->end->execute($env);
        $step = $this->step ? $this->step->execute($env) : 1;
        for ($i = $start; $i <= $end; $i += $step) {
            $env->pushScope();
            $env->setVariable($this->loopVar, $i);
            $result = $this->body->execute($env);
            $env->popScope();
            if ($result instanceof ControlSignal) {
                if ($result->type === 'break') break;
                if ($result->type === 'continue') continue;
                if ($result->type === 'return') return $result;
            }
        }
        return null;
    }
}

// While 循环节点
class WhileNode extends ASTNode {
    public $condition;
    public $body;
    public function execute($env) {
        while ($this->condition->execute($env)) {
            $env->pushScope();
            $result = $this->body->execute($env);
            $env->popScope();
            if ($result instanceof ControlSignal) {
                if ($result->type === 'break') break;
                if ($result->type === 'continue') continue;
                if ($result->type === 'return') return $result;
            }
        }
        return null;
    }
}

// Break 节点
class BreakNode extends ASTNode {
    public function execute($env) {
        return new ControlSignal('break');
    }
}

// Continue 节点
class ContinueNode extends ASTNode {
    public function execute($env) {
        return new ControlSignal('continue');
    }
}

// Return 节点（对应 @ReturnToBot）
class ReturnNode extends ASTNode {
    public $expr;
    public function __construct($expr) {
        $this->expr = $expr;
    }
    public function execute($env) {
        $value = $this->expr ? $this->expr->execute($env) : null;
        return new ControlSignal('return', $value);
    }
}

// @pick 模式匹配节点（语句形式：有 block）
class PickNode extends ASTNode {
    public $varName;
    public $cases; // [value => BlockNode, ...]
    public $default; // BlockNode or null
    public function execute($env) {
        $value = $env->getVariable($this->varName);
        foreach ($this->cases as $caseVal => $block) {
            if ($value == $caseVal) {
                $result = $block->execute($env);
                // 消耗 break/continue 信号（switch 内部使用）
                if ($result instanceof ControlSignal && ($result->type === 'break' || $result->type === 'continue')) {
                    return null;
                }
                return $result;
            }
        }
        if ($this->default) {
            $result = $this->default->execute($env);
            if ($result instanceof ControlSignal && ($result->type === 'break' || $result->type === 'continue')) {
                return null;
            }
            return $result;
        }
        return null;
    }
}

// @pick 表达式节点（表达式形式：@pick(Param:$var)，只提取值）
class PickExprNode extends ExpressionNode {
    public $varName;
    public function __construct($varName) {
        $this->varName = $varName;
    }
    public function execute($env) {
        // 从 Param 中提取指定变量的值
        // Param 是当前函数调用的参数对象
        $param = $env->getVariable('Param');
        if (is_array($param) && isset($param[$this->varName])) {
            return $param[$this->varName];
        }
        // 如果 Param 是值类型，返回本身
        return $param;
    }
}

// @LifeStart 节点（入口表达式，通常为 @RunFunc(...) 或 @pick(...)）
class LifeStartNode extends ASTNode {
    public $expr;
    public function __construct($expr) {
        $this->expr = $expr;
    }
    public function execute($env) {
        return $this->expr->execute($env);
    }
}

// @RunFunc 节点
class RunFuncNode extends ExpressionNode {
    public $funcName;
    public $args;

    public function __construct($funcName, $args = []) {
        $this->funcName = $funcName;
        $this->args = $args;
    }

    public function execute($env) {
        // 提取函数名（如果是 LiteralNode，取其值）
        $funcName = $this->funcName;
        if ($funcName instanceof LiteralNode) {
            $funcName = $funcName->value;
        }
        
        $argValues = [];
        foreach ($this->args as $arg) {
            $argValues[] = $arg->execute($env);
        }
        
        // 如果没有传参数，使用当前 Param 作为参数（递归继承）
        if (empty($argValues)) {
            $param = $env->getVariable('Param');
            if (is_array($param) && isset($param['quantity'])) {
                $argValues = $param['args'];
            } else {
                $argValues = [$param];
            }
        }
        
        $result = $env->callFunction($funcName, $argValues);
        // 保存结果，供 @GetEventInfo(RunFunc, result) 获取
        $env->setRunFuncResult($result);
        if ($result instanceof ControlSignal && $result->type === 'return') {
            $env->setRunFuncResult($result->value);
            return $result->value;
        }
        return $result;
    }
}

// 数组/对象字面量节点 [key => value, key => value, ...]
class MapLiteralNode extends ExpressionNode {
    public $pairs; // [[keyNode, valueNode], ...]
    public function __construct($pairs) {
        $this->pairs = $pairs;
    }
    public function execute($env) {
        $result = [];
        foreach ($this->pairs as $pair) {
            $key = $pair[0]->execute($env);
            $value = $pair[1]->execute($env);
            $result[$key] = $value;
        }
        return $result;
    }
}

// JSON 路径节点 JSON->"path"
class JsonPathNode extends ExpressionNode {
    public $path;
    public function __construct($path) {
        $this->path = $path;
    }
    public function execute($env) {
        // 返回标记对象，供 @GetEventInfo 识别
        return ['__json_path__' => true, 'path' => $this->path];
    }
}

// @EventRestart 节点
class EventRestartNode extends ASTNode {
    public $newPayloadExpr;
    public function __construct($newPayloadExpr) {
        $this->newPayloadExpr = $newPayloadExpr;
    }
    public function execute($env) {
        $newPayload = $this->newPayloadExpr->execute($env);
        // 抛出特殊异常，由 Executor 捕获并重启
        throw new EventRestartException($newPayload);
    }
}

class BinaryOpNode extends ExpressionNode {
    public $left;
    public $operator;
    public $right;

    public function __construct($left, $operator, $right) {
        $this->left = $left;
        $this->operator = $operator;
        $this->right = $right;
    }

    public function execute($env) {
        $left = $this->left->execute($env);
        $right = $this->right->execute($env);
        switch ($this->operator) {
            case '==':  return $left == $right;
            case '!=':  return $left != $right;
            case '<':   return $left < $right;
            case '>':   return $left > $right;
            case '<=':  return $left <= $right;
            case '>=':  return $left >= $right;
            case '&&':  return $left && $right;
            case '||':  return $left || $right;
            // 新增算术运算符
            case '+':  return $left + $right;
            case '-':  return $left - $right;
            case '*':  return $left * $right;
            case '/':  return $left / $right;
            case '%':  return $left % $right;
            default:    throw new Exception("Unknown operator: " . $this->operator);
        }
    }
}

// 自定义异常
class EventRestartException extends Exception {
    public $newPayload;
    public function __construct($payload) {
        $this->newPayload = $payload;
        parent::__construct("EventRestart triggered");
    }
}

