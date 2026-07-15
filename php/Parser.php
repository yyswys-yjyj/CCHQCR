<?php
// Parser.php - 语法分析器（修复参数解析 + IDENTIFIER）

class Parser {
    private $tokens;
    private $pos;
    private $tokenCount;

    public function __construct($tokens) {
        $this->tokens = $tokens;
        $this->pos = 0;
        $this->tokenCount = count($tokens);
    }

    private function current() {
        return $this->tokens[$this->pos] ?? ['type' => 'EOF', 'value' => null];
    }

    private function peek($offset = 0) {
        $idx = $this->pos + $offset;
        return ($idx < $this->tokenCount) ? $this->tokens[$idx] : ['type' => 'EOF', 'value' => null];
    }

    private function next() {
        $this->pos++;
    }

    private function expect($type, $value = null) {
        $token = $this->current();
        if ($token['type'] !== $type || ($value !== null && $token['value'] !== $value)) {
            throw new Exception("Unexpected token: {$token['type']} {$token['value']}, expected $type $value");
        }
        $this->next();
        return $token;
    }

    private function match($type, $value = null) {
        $token = $this->current();
        if ($token['type'] === $type && ($value === null || $token['value'] === $value)) {
            $this->next();
            return true;
        }
        return false;
    }

    public function parse() {
        $program = new ProgramNode();
        while ($this->pos < $this->tokenCount - 1) {
            $token = $this->current();
            if ($token['type'] === 'CONTROL' && $token['value'] === 'Regfunc') {
                $program->functions[] = $this->parseFunctionDef();
            } elseif ($token['type'] === 'CONTROL' && $token['value'] === 'LifeStart') {
                $program->lifeStartExpr = $this->parseLifeStart();
            } else {
                $this->next();
            }
        }
        return $program;
    }

    private function parseFunctionDef() {
        $this->expect('CONTROL', 'Regfunc');
        $signature = '';
        if ($this->match('SYMBOL', '<')) {
            // 提取签名内容
            while ($this->pos < $this->tokenCount && !$this->match('SYMBOL', '>')) {
                $token = $this->current();
                if ($token['type'] === 'SYMBOL' && $token['value'] === '>') break;
                $signature .= $token['value'];
                $this->next();
            }
        }
        $this->expect('IDENTIFIER', 'Param');
        $this->expect('SYMBOL', ':');
        $params = [];
        // 判断参数格式: 类型声明（如 any）或 变量列表（如 $a,$b）
        $nextToken = $this->current();
        if ($nextToken['type'] === 'IDENTIFIER') {
            // 类型声明格式: Param:any& 或 Param:bool&
            $params[] = $nextToken['value']; // 存储类型名作为参数名
            $this->next();
            $this->expect('SYMBOL', '&');
        } else {
            // 变量列表格式: Param:$a,$b&
            while (true) {
                $token = $this->current();
                if ($token['type'] === 'VARIABLE') {
                    $params[] = $token['value'];
                    $this->next();
                    if ($this->match('SYMBOL', ',')) continue;
                    elseif ($this->match('SYMBOL', '&')) break;
                    else throw new Exception("Expected ',' or '&' after parameter");
                } elseif ($token['type'] === 'SYMBOL' && $token['value'] === '&') {
                    $this->next();
                    break;
                } else {
                    throw new Exception("Expected variable or &");
                }
            }
        }
        $body = $this->parseBlock();
        $name = $this->extractFunctionName($body);
        if (!$name) throw new Exception("Function definition missing @SetCallBackName");
        $func = new FunctionDefNode();
        $func->name = $name;
        $func->signature = $signature;
        $func->params = $params;
        $func->paramCount = count($params);
        $func->body = $body;
        return $func;
    }

    private function extractFunctionName($block) {
        $foundName = null;
        foreach ($block->statements as $stmt) {
            if ($stmt instanceof CallNode && $stmt->name === 'SetCallBackName') {
                if ($foundName !== null) {
                    throw new Exception("Multiple @SetCallBackName declarations in one function");
                }
                if (isset($stmt->args[0]) && $stmt->args[0] instanceof LiteralNode) {
                    $foundName = $stmt->args[0]->value;
                }
            }
        }
        return $foundName;
    }

    private function parseBlock() {
        $this->expect('SYMBOL', '{');
        $block = new BlockNode();
        while (!$this->match('SYMBOL', '}')) {
            $stmt = $this->parseStatement();
            if ($stmt) $block->statements[] = $stmt;
        }
        return $block;
    }

    private function parseStatement() {
        $token = $this->current();
        
        // 空语句
        if ($this->match('SYMBOL', ';') || $this->match('SYMBOL', '&')) {
            return null;
        }
        
        // @Regfunc 函数定义（支持在 block 内动态注册）
        if ($token['type'] === 'CONTROL' && $token['value'] === 'Regfunc') {
            return $this->parseFunctionDef();
        }
        
        // @EventRestart 特殊处理（必须在 CONTROL 分支之前）
        if ($token['type'] === 'CONTROL' && $token['value'] === 'EventRestart') {
            $this->next();
            $this->expect('SYMBOL', '(');
            $expr = $this->parseExpression();
            $this->expect('SYMBOL', ')');
            $this->match('SYMBOL', ';') || $this->match('SYMBOL', '&');
            return new EventRestartNode($expr);
        }
        
        // 特殊处理 @pick (模式匹配)
        if ($token['type'] === 'CONTROL' && $token['value'] === 'pick') {
            $node = $this->parsePick();
            $this->match('SYMBOL', ';') || $this->match('SYMBOL', '&');
            return $node;
        }
        
        // 控制流关键字
        if ($token['type'] === 'KEYWORD') {
            switch ($token['value']) {
                case 'if':
                    return $this->parseIf();
                case 'for':
                    return $this->parseFor();
                case 'while':
                    return $this->parseWhile();
                case 'break':
                    $this->next();
                    $this->match('SYMBOL', ';') || $this->match('SYMBOL', '&');
                    return new BreakNode();
                case 'continue':
                    $this->next();
                    $this->match('SYMBOL', ';') || $this->match('SYMBOL', '&');
                    return new ContinueNode();
                default:
                    // 可能是普通标识符或变量赋值
            }
        }
    
        // 变量赋值：$var = expr;
        if ($token['type'] === 'VARIABLE') {
            $varName = $token['value'];
            $this->next();
            $this->expect('SYMBOL', '=');
            $expr = $this->parseExpression();
            $this->match('SYMBOL', ';') || $this->match('SYMBOL', '&');
            return new AssignNode($varName, $expr);
        }
    
        // 控件调用：@Name(...)
        if ($token['type'] === 'CONTROL') {
            $name = $token['value'];
            $this->next();
            $args = $this->parseArgs();
            $this->match('SYMBOL', ';') || $this->match('SYMBOL', '&');
            // 特殊处理 RunFunc（作为语句时）
            if ($name === 'RunFunc') {
                if (count($args) < 1) throw new Exception("RunFunc requires at least function name");
                $funcName = $args[0];
                $args = array_slice($args, 1);
                return new RunFuncNode($funcName, $args);
            }
            return new CallNode($name, $args);
        }
    
        // 其他表达式（如 @ReturnToBot 等）
        if ($token['type'] === 'CONTROL') {
            $expr = $this->parseExpression();
            $this->match('SYMBOL', ';') || $this->match('SYMBOL', '&');
            return $expr;
        }
    
        throw new Exception("Unexpected token in statement: " . $token['type'] . " " . $token['value']);
    }

    private function parseIf() {
        $this->expect('KEYWORD', 'if');
        $this->expect('SYMBOL', '(');
        $cond = $this->parseExpression();
        $this->expect('SYMBOL', ')');
        $thenBlock = $this->parseBlock();
        $elseBlock = null;
        if ($this->match('KEYWORD', 'else')) {
            if ($this->match('KEYWORD', 'if')) {
                $elseIf = $this->parseIf();
                $elseBlock = new BlockNode();
                $elseBlock->statements[] = $elseIf;
            } else {
                $elseBlock = $this->parseBlock();
            }
        }
        $node = new IfNode();
        $node->condition = $cond;
        $node->thenBranch = $thenBlock;
        $node->elseBranch = $elseBlock;
        return $node;
    }

    private function parseFor() {
        $this->expect('KEYWORD', 'for');
        $this->expect('SYMBOL', '(');
        $start = $this->parseExpression();
        $this->expect('SYMBOL', ':');
        $end = $this->parseExpression();
        $step = null;
        if ($this->match('SYMBOL', ':')) {
            $step = $this->parseExpression();
        }
        $this->expect('SYMBOL', ')');
        $body = $this->parseBlock();
        $node = new ForNode();
        $node->start = $start;
        $node->end = $end;
        $node->step = $step;
        $node->body = $body;
        return $node;
    }

    private function parseWhile() {
        $this->expect('KEYWORD', 'while');
        $this->expect('SYMBOL', '(');
        $cond = $this->parseExpression();
        $this->expect('SYMBOL', ')');
        $body = $this->parseBlock();
        $node = new WhileNode();
        $node->condition = $cond;
        $node->body = $body;
        return $node;
    }

    private function parseLifeStart() {
        $this->expect('CONTROL', 'LifeStart');
        $this->expect('SYMBOL', '(');
        $expr = $this->parseExpression();
        $this->expect('SYMBOL', ')');
        return new LifeStartNode($expr);
    }

    // 修复：正确解析参数列表
    private function parseArgs() {
        $args = [];
        if ($this->match('SYMBOL', '(')) {
            if (!$this->match('SYMBOL', ')')) {
                $args[] = $this->parseExpression();
                while ($this->match('SYMBOL', ',')) {
                    $args[] = $this->parseExpression();
                }
                $this->expect('SYMBOL', ')');
            }
        }
        return $args;
    }

    // 解析基础原子（增加 IDENTIFIER 支持）
    private function parsePrimary() {
        $token = $this->current();

        if ($token['type'] === 'STRING' || $token['type'] === 'NUMBER') {
            $value = $token['value'];
            $this->next();
            if ($token['type'] === 'NUMBER') {
                $value = (strpos($value, '.') !== false) ? (float)$value : (int)$value;
            }
            return new LiteralNode($value);
        }

        // 布尔字面量 true / false
        if ($token['type'] === 'KEYWORD' && ($token['value'] === 'true' || $token['value'] === 'false')) {
            $value = ($token['value'] === 'true');
            $this->next();
            return new LiteralNode($value);
        }

        if ($token['type'] === 'VARIABLE') {
            $name = $token['value'];
            $this->next();
            return new VariableNode($name);
        }
        
         // 处理一元运算符 ! 和 -
        if ($token['type'] === 'SYMBOL' && ($token['value'] === '!' || $token['value'] === '-')) {
            $operator = $token['value'];
            $this->next();
            $operand = $this->parseExpression(); // 递归解析操作数
            return new UnaryOpNode($operator, $operand);
        }

        // @pick 特殊处理（必须在 CONTROL 分支之前）
        if ($token['type'] === 'CONTROL' && $token['value'] === 'pick') {
            return $this->parsePick();
        }

        // @EventRestart
        if ($token['type'] === 'CONTROL' && $token['value'] === 'EventRestart') {
            $this->next();
            $this->expect('SYMBOL', '(');
            $expr = $this->parseExpression();
            $this->expect('SYMBOL', ')');
            return new EventRestartNode($expr);
        }

        // 标识符（如函数名等）作为字符串字面量处理
        if ($token['type'] === 'IDENTIFIER') {
            $value = $token['value'];
            $this->next();
            // 处理 JSON->"path" 表达式
            if ($value === 'JSON' && $this->current()['type'] === 'SYMBOL' && $this->current()['value'] === '->') {
                $this->next(); // 消费 ->
                $pathToken = $this->current();
                if ($pathToken['type'] !== 'STRING') {
                    throw new Exception("Expected string after JSON->");
                }
                $path = $pathToken['value'];
                $this->next();
                return new JsonPathNode($path);
            }
            return new LiteralNode($value);
        }

        if ($token['type'] === 'CONTROL') {
            $name = $token['value'];
            $this->next();
            $args = $this->parseArgs();
            // 特殊处理 RunFunc（直接返回 RunFuncNode）
            if ($name === 'RunFunc') {
                if (count($args) < 1) throw new Exception("RunFunc requires at least function name");
                $funcName = $args[0];
                $args = array_slice($args, 1);
                return new RunFuncNode($funcName, $args);
            }
            return new CallNode($name, $args);
        }

        // 括号表达式
        if ($token['type'] === 'SYMBOL' && $token['value'] === '(') {
            $this->next();
            $expr = $this->parseExpression();
            $this->expect('SYMBOL', ')');
            return $expr;
        }

        // 数组/对象字面量 [key => value, ...] 或 [value, value, ...]
        if ($token['type'] === 'SYMBOL' && $token['value'] === '[') {
            return $this->parseMapLiteral();
        }

        throw new Exception("Unexpected token in expression: " . $token['type'] . " " . $token['value']);
    }

    // parseExpression（支持二元运算）
    private function parseExpression() {
        $left = $this->parsePrimary();
        $token = $this->current();
        $binaryOps = ['==','!=','<','>','<=','>=','&&','||','+','-','*','/','%'];
        if ($token['type'] === 'SYMBOL' && in_array($token['value'], $binaryOps)) {
            $operator = $token['value'];
            $this->next();
            $right = $this->parseExpression();
            return new BinaryOpNode($left, $operator, $right);
        }
        return $left;
    }

    private function parsePick() {
        $this->expect('CONTROL', 'pick');
        $this->expect('SYMBOL', '(');
        $this->expect('IDENTIFIER', 'Param');
        $this->expect('SYMBOL', ':');
        $varToken = $this->current();
        if ($varToken['type'] !== 'VARIABLE') throw new Exception("Expected variable after Param:");
        $varName = $varToken['value'];
        $this->next();
        $this->expect('SYMBOL', ')');
        
        // 检查是否有 block - 没有则视为表达式形式
        if (!$this->match('SYMBOL', '{')) {
            return new PickExprNode($varName);
        }
        
        // 语句形式：@pick(Param:$var){switch($var){...}}
        $this->expect('KEYWORD', 'switch');
        $this->expect('SYMBOL', '(');
        $this->parseExpression(); // 可忽略
        $this->expect('SYMBOL', ')');
        $this->expect('SYMBOL', '{');
        $cases = [];
        $default = null;
        while (!$this->match('SYMBOL', '}')) {
            if ($this->match('KEYWORD', 'case')) {
                $caseValToken = $this->current();
                if ($caseValToken['type'] !== 'STRING' && $caseValToken['type'] !== 'NUMBER') {
                    throw new Exception("Case value must be literal");
                }
                $caseVal = $caseValToken['value'];
                $this->next();
                $this->expect('SYMBOL', ':');
                $block = $this->parseBlock();
                $cases[$caseVal] = $block;
            } elseif ($this->match('KEYWORD', 'default')) {
                $this->expect('SYMBOL', ':');
                $default = $this->parseBlock();
            } else {
                throw new Exception("Expected case or default");
            }
        }
        $this->expect('SYMBOL', '}');
        $node = new PickNode();
        $node->varName = $varName;
        $node->cases = $cases;
        $node->default = $default;
        return $node;
    }

    // 解析数组/对象字面量 [key => value, ...] 或 [value, value, ...]
    private function parseMapLiteral() {
        $this->expect('SYMBOL', '[');
        $pairs = [];
        // 空数组
        if ($this->match('SYMBOL', ']')) {
            return new MapLiteralNode($pairs);
        }
        
        $index = 0;        // 值列表格式的自动数字索引
        $isKeyValue = false; // 是否为键值对格式
        
        // 解析第一个元素
        $first = $this->parseExpression();
        if ($this->match('SYMBOL', '=>')) {
            $isKeyValue = true;
            $value = $this->parseExpression();
            $pairs[] = [$first, $value];
        } else {
            $pairs[] = [new LiteralNode($index++), $first];
        }
        
        // 继续解析剩余元素
        while ($this->match('SYMBOL', ',')) {
            // 允许尾部逗号: [,]
            if ($this->match('SYMBOL', ']')) {
                return new MapLiteralNode($pairs);
            }
            
            if ($isKeyValue) {
                $key = $this->parseExpression();
                $this->expect('SYMBOL', '=>');
                $val = $this->parseExpression();
                $pairs[] = [$key, $val];
            } else {
                $val = $this->parseExpression();
                $pairs[] = [new LiteralNode($index++), $val];
            }
        }
        
        $this->expect('SYMBOL', ']');
        return new MapLiteralNode($pairs);
    }
}