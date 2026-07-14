<?php
// Builtins.php - 内置函数注册

class Builtins {
    public static function register($env) {
        // 注册控件 @GetEventInfo
        $env->registerControl('GetEventInfo', function($data, $path = null) use ($env) {
            if ($path === null) return $data;
            // 支持点路径
            $parts = explode('.', $path);
            $current = $data;
            foreach ($parts as $key) {
                if (is_array($current) && isset($current[$key])) {
                    $current = $current[$key];
                } else {
                    return null;
                }
            }
            return $current;
        });

        // 注册控件 @ReturnToBot
        $env->registerControl('ReturnToBot', function($value) {
            // 返回控制信号
            return new ControlSignal('return', $value);
        });

        // 注册控件 @Log
        $env->registerControl('Log', function($message) {
            error_log("[CCHQ] $message");
            return null;
        });

        // 注册控件 @SetCallBackName （由编译器特殊处理，这里空实现）
        $env->registerControl('SetCallBackName', function($name) {
            // 在函数定义中已经被提取，运行时无需操作
            return null;
        });

        // 可以注册其他默认控件...
    }
}