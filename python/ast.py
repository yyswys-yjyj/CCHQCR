"""CCHQCode Runtime - AST 节点定义"""

from abc import ABC, abstractmethod
from typing import Any, Optional, List, Tuple

from .types import ControlSignal, EventRestartSignal


class ASTNode(ABC):
    @abstractmethod
    def execute(self, env) -> Any: ...


class ExpressionNode(ASTNode):
    """表达式基类"""
    pass


# ===== 程序根节点 =====
class ProgramNode(ASTNode):
    def __init__(self):
        self.functions: List['FunctionDefNode'] = []
        self.life_start_expr: Optional[ASTNode] = None

    def execute(self, env):
        for func in self.functions:
            env.register_function(func.name, func, func.param_count)
        if self.life_start_expr:
            return self.life_start_expr.execute(env)
        return None


# ===== 函数定义 =====
class FunctionDefNode(ASTNode):
    def __init__(self):
        self.name = ''
        self.signature = ''
        self.params: List[str] = []
        self.param_count = 0
        self.body: Optional['BlockNode'] = None

    def execute(self, env):
        env.register_function(self.name, self, self.param_count)
        return None


# ===== 块节点 =====
class BlockNode(ASTNode):
    def __init__(self):
        self.statements: List[ASTNode] = []

    def execute(self, env):
        return_val = None
        for stmt in self.statements:
            result = stmt.execute(env)
            if isinstance(result, ControlSignal):
                return result
            return_val = result
        return return_val


# ===== 字面量 =====
class LiteralNode(ExpressionNode):
    def __init__(self, value: Any):
        super().__init__()
        self.value = value

    def execute(self, env):
        return self.value


# ===== 一元运算 =====
class UnaryOpNode(ExpressionNode):
    def __init__(self, operator: str, operand: ASTNode):
        super().__init__()
        self.operator = operator
        self.operand = operand

    def execute(self, env):
        val = self.operand.execute(env)
        if self.operator == '!':
            return not val
        elif self.operator == '-':
            return -val
        raise ValueError(f"Unknown unary operator: {self.operator}")


# ===== 变量 =====
class VariableNode(ExpressionNode):
    def __init__(self, name: str):
        super().__init__()
        self.name = name

    def execute(self, env):
        return env.get_variable(self.name)


# ===== 赋值 =====
class AssignNode(ExpressionNode):
    def __init__(self, var_name: str, expr: ASTNode):
        super().__init__()
        self.var_name = var_name
        self.expr = expr

    def execute(self, env):
        value = self.expr.execute(env)
        env.set_variable(self.var_name, value)
        return value


# ===== 二元运算 =====
class BinaryOpNode(ExpressionNode):
    def __init__(self, left: ASTNode, operator: str, right: ASTNode):
        super().__init__()
        self.left = left
        self.operator = operator
        self.right = right

    def execute(self, env):
        l_val = self.left.execute(env)
        r_val = self.right.execute(env)
        op = self.operator
        if op == '==': return l_val == r_val
        if op == '!=': return l_val != r_val
        if op == '<':  return l_val < r_val
        if op == '>':  return l_val > r_val
        if op == '<=': return l_val <= r_val
        if op == '>=': return l_val >= r_val
        if op == '&&': return l_val and r_val
        if op == '||': return l_val or r_val
        if op == '+':  return l_val + r_val
        if op == '-':  return l_val - r_val
        if op == '*':  return l_val * r_val
        if op == '/':  return l_val / r_val
        if op == '%':  return l_val % r_val
        raise ValueError(f"Unknown operator: {op}")


# ===== 调用节点 =====
class CallNode(ExpressionNode):
    def __init__(self, name: str, args: List[ASTNode]):
        super().__init__()
        self.name = name
        self.args = args

    def execute(self, env):
        arg_values = [a.execute(env) for a in self.args]
        if env.has_control(self.name):
            return env.call_control(self.name, arg_values)
        if env.has_function(self.name, len(arg_values)):
            return env.call_function(self.name, arg_values)
        raise ValueError(f"Undefined callable: {self.name}")


# ===== If 节点 =====
class IfNode(ASTNode):
    def __init__(self):
        self.condition: Optional[ASTNode] = None
        self.then_branch: Optional[ASTNode] = None
        self.else_branch: Optional[ASTNode] = None

    def execute(self, env):
        cond = self.condition.execute(env)
        if cond:
            return self.then_branch.execute(env)
        elif self.else_branch:
            return self.else_branch.execute(env)
        return None


# ===== For 循环 =====
class ForNode(ASTNode):
    def __init__(self):
        self.start: Optional[ASTNode] = None
        self.end: Optional[ASTNode] = None
        self.step: Optional[ASTNode] = None
        self.body: Optional[ASTNode] = None
        self.loop_var = 'i'

    def execute(self, env):
        start_val = int(self.start.execute(env))
        end_val = int(self.end.execute(env))
        step_val = int(self.step.execute(env)) if self.step else 1

        i = start_val
        while i <= end_val:
            env.push_scope()
            env.set_variable(self.loop_var, i)
            result = self.body.execute(env)
            env.pop_scope()
            if isinstance(result, ControlSignal):
                if result.type == 'break':
                    break
                if result.type == 'continue':
                    i += step_val
                    continue
                if result.type == 'return':
                    return result
            i += step_val
        return None


# ===== While 循环 =====
class WhileNode(ASTNode):
    def __init__(self):
        self.condition: Optional[ASTNode] = None
        self.body: Optional[ASTNode] = None

    def execute(self, env):
        while self.condition.execute(env):
            env.push_scope()
            result = self.body.execute(env)
            env.pop_scope()
            if isinstance(result, ControlSignal):
                if result.type == 'break':
                    break
                if result.type == 'continue':
                    continue
                if result.type == 'return':
                    return result
        return None


# ===== Break / Continue =====
class BreakNode(ASTNode):
    def execute(self, env):
        return ControlSignal('break')


class ContinueNode(ASTNode):
    def execute(self, env):
        return ControlSignal('continue')


# ===== @pick 模式匹配（语句形式） =====
class PickNode(ASTNode):
    def __init__(self):
        self.var_name = ''
        self.cases: dict = {}
        self.default: Optional[BlockNode] = None

    def execute(self, env):
        value = env.get_variable(self.var_name)
        for case_val, block in self.cases.items():
            if value == case_val:
                result = block.execute(env)
                if isinstance(result, ControlSignal) and result.type in ('break', 'continue'):
                    return None
                return result
        if self.default:
            result = self.default.execute(env)
            if isinstance(result, ControlSignal) and result.type in ('break', 'continue'):
                return None
            return result
        return None


# ===== @pick 表达式形式 =====
class PickExprNode(ExpressionNode):
    def __init__(self, var_name: str):
        super().__init__()
        self.var_name = var_name

    def execute(self, env):
        param = env.get_variable('Param')
        if isinstance(param, dict) and self.var_name in param:
            return param[self.var_name]
        return param


# ===== 数组字面量 =====
class MapLiteralNode(ExpressionNode):
    def __init__(self, pairs: List[Tuple[ASTNode, ASTNode]]):
        super().__init__()
        self.pairs = pairs

    def execute(self, env):
        result = {}
        for key_node, val_node in self.pairs:
            key = key_node.execute(env)
            value = val_node.execute(env)
            result[key] = value
        return result


# ===== @LifeStart =====
class LifeStartNode(ASTNode):
    def __init__(self, expr: ASTNode):
        super().__init__()
        self.expr = expr

    def execute(self, env):
        return self.expr.execute(env)


# ===== @RunFunc =====
class RunFuncNode(ExpressionNode):
    def __init__(self, func_name: ASTNode, args: List[ASTNode]):
        super().__init__()
        self.func_name = func_name
        self.args = args

    def execute(self, env):
        fname = self.func_name
        if isinstance(fname, LiteralNode):
            fname = fname.value
        else:
            fname = fname.execute(env)

        arg_values = [a.execute(env) for a in self.args]

        # 无参数时继承当前 Param
        if not arg_values:
            param = env.get_variable('Param')
            if isinstance(param, dict) and 'quantity' in param:
                arg_values = list(param['args'])
            else:
                arg_values = [param]

        result = env.call_function(fname, arg_values)
        env.set_run_func_result(result)
        if isinstance(result, ControlSignal) and result.type == 'return':
            env.set_run_func_result(result.value)
            return result.value
        return result


# ===== @EventRestart =====
class EventRestartNode(ASTNode):
    def __init__(self, expr: ASTNode):
        super().__init__()
        self.expr = expr

    def execute(self, env):
        new_payload = self.expr.execute(env)
        raise EventRestartSignal(new_payload)
