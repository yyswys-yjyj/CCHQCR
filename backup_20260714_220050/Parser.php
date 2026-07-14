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
        if ($this->match('SYMBOL', '<')) {
            while (!$this->match('SYMBOL', '>')) { $this->next(); }
        }
        $this->expect('KEYWORD', 'Param');
        $this->expect('SYMBOL', ':');
        $params = [];
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
        $body = $this->parseBlock();
        $name = $this->extractFunctionName($body);
        if (!$name) throw new Exception("Function definition missing @SetCallBackName");
        $func = new FunctionDefNode();
        $func->name = $name;
        $func->params = $params;
        $func->paramCount = count($params);
        $func->body = $body;
        return $func;
    }

    private function extractFunctionName($block) {
        foreach ($block->statements as $stmt) {
            if ($stmt instanceof CallNode && $stmt->name === 'SetCallBackName') {
                if (isset($stmt->args[0]) && $stmt->args[0] instanceof LiteralNode) {
                    return $stmt->args[0]->value;
                }
            }
        }
        return null;
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

        // 标识符（如函数名等）作为字符串字面量处理
        if ($token['type'] === 'IDENTIFIER') {
            $value = $token['value'];
            $this->next();
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

        // @pick
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

        // 括号表达式
        if ($token['type'] === 'SYMBOL' && $token['value'] === '(') {
            $this->next();
            $expr = $this->parseExpression();
            $this->expect('SYMBOL', ')');
            return $expr;
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
        $this->expect('KEYWORD', 'Param');
        $this->expect('SYMBOL', ':');
        $varToken = $this->current();
        if ($varToken['type'] !== 'VARIABLE') throw new Exception("Expected variable after Param:");
        $varName = $varToken['value'];
        $this->next();
        $this->expect('SYMBOL', ')');
        $this->expect('SYMBOL', '{');
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
}