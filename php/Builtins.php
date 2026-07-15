<?php
// Builtins.php - 内置函数注册

class Builtins {
    public static function register($env) {
        // 注册控件 @GetEventInfo
        $env->registerControl('GetEventInfo', function($data, $path = null) use ($env) {
            // 特殊处理 @GetEventInfo(RunFunc, result) - 获取最近一次 @RunFunc 结果
            if ($data === 'RunFunc' && $path === 'result') {
                return $env->getRunFuncResult();
            }
            // 特殊处理 @GetEventInfo(Param, ...) - 获取当前函数调用参数信息
            if ($data === 'Param') {
                $param = $env->getVariable('Param');
                if ($path === null) return $param;
                if ($path === 'quantity') {
                    if (is_array($param) && isset($param['quantity'])) {
                        return $param['quantity'];
                    }
                    return 1;
                }
                if (is_array($param) && isset($param[$path])) {
                    return $param[$path];
                }
                return $param;
            }
            // JSON 路径模式: @GetEventInfo(JSON->"path", $source)
            // $data 是标记对象 { __json_path__: true, path: "xxx" }
            if (is_array($data) && isset($data['__json_path__']) && $data['__json_path__'] === true) {
                $jsonStr = $path;
                if (is_string($jsonStr)) {
                    $parsed = json_decode($jsonStr, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                        $parts = explode('.', $data['path']);
                        $current = $parsed;
                        foreach ($parts as $key) {
                            if (is_array($current) && isset($current[$key])) {
                                $current = $current[$key];
                            } else {
                                return null;
                            }
                        }
                        return $current;
                    }
                }
                return null;
            }
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

        // 注册控件 @ReturnBack
        $env->registerControl('ReturnBack', function($value) {
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