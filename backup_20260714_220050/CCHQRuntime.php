<?php
// CCHQRuntime.php - 入口类

require_once __DIR__ . '/Lexer.php';
require_once __DIR__ . '/Parser.php';
require_once __DIR__ . '/AST.php';
require_once __DIR__ . '/Environment.php';
require_once __DIR__ . '/Executor.php';
require_once __DIR__ . '/Builtins.php';

class CCHQRuntime {
    private $executor;
    private $environment;

    /**
     * 构造函数
     * @param string $script CCHQ 源代码
     * @param array $context 初始上下文（如 Webhook Payload）
     */
    public function __construct($script, $context = []) {
        // 初始化环境
        $this->environment = new Environment($context);
        // 注册内置函数
        Builtins::register($this->environment);
        // 词法分析
        $lexer = new Lexer($script);
        $tokens = $lexer->tokenize();
        // 语法分析
        $parser = new Parser($tokens);
        $ast = $parser->parse();
        // 执行器
        $this->executor = new Executor($this->environment, $ast);
    }

    /**
     * 执行脚本入口（@LifeStart）
     * @return mixed 脚本返回值
     */
    public function run() {
        return $this->executor->run();
    }

    /**
     * 注册自定义控件（例如 @OpenAIHttp）
     * @param string $name 控件名称（不含 @）
     * @param callable $callable 回调函数，接收参数列表
     */
    public function registerControl($name, callable $callable) {
        $this->environment->registerControl($name, $callable);
    }

    /**
     * 获取环境（用于调试）
     */
    public function getEnvironment() {
        return $this->environment;
    }
}