"""CCHQCode Runtime - 运行时环境"""

from typing import Any, List, Optional

from .types import ControlSignal, EventRestartSignal

TYPE_KEYWORDS = {
    'any', 'bool', 'string', 'int', 'number',
    'float', 'double', 'array', 'object',
    'callable', 'void', 'null', 'mixed',
}


class Environment:
    def __init__(self, context: Any = None):
        self.context = context if context is not None else {}
        self.scope_stack: List[dict] = []
        self.functions: dict = {}
        self.controls: dict = {}
        self._last_run_func_result = None

        self.push_scope()
        if isinstance(self.context, dict):
            for k, v in self.context.items():
                self.set_variable(k, v)

    # ---------- 作用域 ----------
    def push_scope(self):
        self.scope_stack.append({})

    def pop_scope(self):
        self.scope_stack.pop()

    def set_variable(self, name: str, value: Any):
        # 如果变量在父作用域已存在，修改父作用域中的值
        for i in range(len(self.scope_stack) - 2, -1, -1):
            if name in self.scope_stack[i]:
                self.scope_stack[i][name] = value
                return
        # 否则在当前作用域创建
        self.scope_stack[-1][name] = value

    def get_variable(self, name: str) -> Any:
        for scope in reversed(self.scope_stack):
            if name in scope:
                return scope[name]
        # 回退到上下文
        if isinstance(self.context, dict) and name in self.context:
            return self.context[name]
        raise ValueError(f"Undefined variable: {name}")

    # ---------- 函数 ----------
    def register_function(self, name: str, def_, arg_count: int):
        if name not in self.functions:
            self.functions[name] = {}
        self.functions[name][arg_count] = def_

    def has_function(self, name: str, arg_count: int) -> bool:
        return name in self.functions and arg_count in self.functions[name]

    def call_function(self, name: str, args: List[Any]) -> Any:
        if not isinstance(name, str):
            raise TypeError(f"Function name must be a string, got {type(name)}")
        arg_count = len(args)
        if not self.has_function(name, arg_count):
            raise ValueError(f"Function {name} with {arg_count} arguments not found")

        def_ = self.functions[name][arg_count]
        # 鸭子类型检查: 有 body 和 params 就是函数定义节点
        if hasattr(def_, 'body') and hasattr(def_, 'params'):
            self.push_scope()
            params = def_.params
            is_type_param = len(params) == 1 and params[0] in TYPE_KEYWORDS

            if is_type_param:
                self.set_variable('Param', args[0] if args else None)
            else:
                self.set_variable('Param', {
                    'quantity': arg_count,
                    'args': list(args),
                })
            for idx, pname in enumerate(params):
                self.set_variable(pname, args[idx] if idx < len(args) else None)

            try:
                result = def_.body.execute(self)
                self.pop_scope()
                return result
            except EventRestartSignal as e:
                self.pop_scope()
                return self.call_function(name, [e.new_payload])
        elif callable(def_):
            return def_(*args)

        raise ValueError("Invalid function definition")

    # ---------- 控件 ----------
    def register_control(self, name: str, callable_):
        self.controls[name] = callable_

    def has_control(self, name: str) -> bool:
        return name in self.controls

    def call_control(self, name: str, args: List[Any]) -> Any:
        if not self.has_control(name):
            raise ValueError(f"Control {name} not registered")
        return self.controls[name](*args)

    # ---------- 上下文 ----------
    def get_context(self) -> Any:
        return self.context

    def set_context(self, ctx: Any):
        self.context = ctx

    def reset_scopes(self):
        self.scope_stack = []
        self.push_scope()
        if isinstance(self.context, dict):
            for k, v in self.context.items():
                self.set_variable(k, v)
        else:
            self.set_variable('payload', self.context)

    # ---------- RunFunc 结果 ----------
    def set_run_func_result(self, result: Any):
        self._last_run_func_result = result

    def get_run_func_result(self) -> Any:
        return self._last_run_func_result
