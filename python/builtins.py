"""CCHQCode Runtime - 内置函数"""

from .types import ControlSignal


def register_builtins(env):
    """注册所有内置函数（控件）"""

    # @GetEventInfo
    def get_event_info(data, path=None):
        # 特殊处理 @GetEventInfo(RunFunc, result)
        if data == 'RunFunc' and path == 'result':
            return env.get_run_func_result()
        # 特殊处理 @GetEventInfo(Param, ...)
        if data == 'Param':
            param = env.get_variable('Param')
            if path is None:
                return param
            if path == 'quantity':
                if isinstance(param, dict) and 'quantity' in param:
                    return param['quantity']
                return 1
            if isinstance(param, dict) and path in param:
                return param[path]
            return param
        # JSON 路径模式: @GetEventInfo(JSON->"path", $source)
        if isinstance(data, dict) and data.get('__json_path__') is True:
            json_str = path
            if isinstance(json_str, str):
                import json
                try:
                    parsed = json.loads(json_str)
                    if isinstance(parsed, dict):
                        parts = data['path'].split('.')
                        current = parsed
                        for key in parts:
                            if isinstance(current, dict) and key in current:
                                current = current[key]
                            else:
                                return None
                        return current
                except json.JSONDecodeError:
                    return None
            return None
        if path is None:
            return data
        # 点路径
        parts = path.split('.')
        current = data
        for key in parts:
            if isinstance(current, dict) and key in current:
                current = current[key]
            else:
                return None
        return current

    env.register_control('GetEventInfo', get_event_info)

    # @ReturnBack
    def return_back(value):
        return ControlSignal('return', value)
    env.register_control('ReturnBack', return_back)

    # @Log
    def log(message):
        print(f"[CCHQ] {message}")
        return None
    env.register_control('Log', log)

    # @SetCallBackName（运行时无需操作）
    def set_callback_name(name):
        return None
    env.register_control('SetCallBackName', set_callback_name)
