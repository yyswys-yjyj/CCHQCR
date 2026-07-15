<?php
// Executor.php - 执行器

class Executor {
    private $environment;
    private $program;

    public function __construct($environment, $program) {
        $this->environment = $environment;
        $this->program = $program;
    }

    public function run() {
        try {
            // 先注册所有函数（ProgramNode已做）
            // 执行程序
            $result = $this->program->execute($this->environment);
            // 如果结果是控制信号，提取返回值（如果 return）
            if ($result instanceof ControlSignal && $result->type === 'return') {
                return $result->value;
            }
            return $result;
        } catch (EventRestartException $e) {
            // @EventRestart 在函数体内被捕获处理，不应传播到此处
            throw $e;
        }
    }
}