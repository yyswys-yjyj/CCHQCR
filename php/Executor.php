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
        while (true) {
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
                // 重启：更新上下文，重置作用域，继续循环重新执行
                $this->environment->setContext($e->newPayload);
                $this->environment->resetScopes();
                // 继续循环，重新执行
            }
        }
    }
}