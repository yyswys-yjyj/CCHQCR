<?php
// Environment.php - 运行时环境

class Environment {
    private $context;          // 全局上下文（如 payload）
    private $variables = [];   // 变量栈（作用域）
    private $functions = [];   // 函数表: [name][argCount] = FunctionDefNode|callable
    private $controls = [];    // 控件表: [name] => callable
    private $scopeStack = [];

    public function __construct($context = []) {
        $this->context = $context;
        $this->pushScope();
        foreach ($context as $key => $value) {
            $this->setVariable($key, $value);
        }
    }

    // ---------- 变量 ----------
    public function pushScope() {
        $this->scopeStack[] = [];
    }
    public function popScope() {
        array_pop($this->scopeStack);
    }
    public function setVariable($name, $value) {
        $this->scopeStack[count($this->scopeStack)-1][$name] = $value;
    }
    public function getVariable($name) {
        for ($i = count($this->scopeStack) - 1; $i >= 0; $i--) {
            if (isset($this->scopeStack[$i][$name])) {
                return $this->scopeStack[$i][$name];
            }
        }
        // 回退到上下文
        if (isset($this->context[$name])) {
            return $this->context[$name];
        }
        throw new Exception("Undefined variable: $name");
    }

    // ---------- 函数 ----------
    public function registerFunction($name, $def, $argCount) {
        if (!isset($this->functions[$name])) {
            $this->functions[$name] = [];
        }
        $this->functions[$name][$argCount] = $def;
    }
    public function hasFunction($name, $argCount) {
        if (!is_string($name)) {
            throw new Exception("Function name must be a string, got " . gettype($name));
        }
        return isset($this->functions[$name]) && isset($this->functions[$name][$argCount]);
    }
    public function callFunction($name, $args) {
        if (!is_string($name)) {
            throw new Exception("Function name must be a string, got " . gettype($name));
        }
        $argCount = count($args);
        if (!$this->hasFunction($name, $argCount)) {
            throw new Exception("Function $name with $argCount arguments not found");
        }
        $def = $this->functions[$name][$argCount];
        if ($def instanceof FunctionDefNode) {
            // 创建新的作用域，绑定参数
            $this->pushScope();
            foreach ($def->params as $idx => $paramName) {
                $this->setVariable($paramName, $args[$idx] ?? null);
            }
            $result = $def->body->execute($this);
            $this->popScope();
            return $result;
        } elseif (is_callable($def)) {
            return call_user_func_array($def, $args);
        }
        throw new Exception("Invalid function definition");
    }

    // ---------- 控件 ----------
    public function registerControl($name, callable $callable) {
        $this->controls[$name] = $callable;
    }
    public function hasControl($name) {
        return isset($this->controls[$name]);
    }
    public function callControl($name, $args) {
        if (!$this->hasControl($name)) {
            throw new Exception("Control $name not registered");
        }
        return call_user_func_array($this->controls[$name], $args);
    }

    // 获取上下文
    public function getContext() {
        return $this->context;
    }
    public function setContext($newContext) {
        $this->context = $newContext;
    }
}