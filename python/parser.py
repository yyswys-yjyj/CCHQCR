"""CCHQCode Runtime - 语法分析器"""

from typing import List, Optional

from .lexer import Token
from .ast import (
    ASTNode, ProgramNode, FunctionDefNode, BlockNode,
    LiteralNode, VariableNode, AssignNode, CallNode,
    IfNode, ForNode, WhileNode, BreakNode, ContinueNode,
    PickNode, PickExprNode, MapLiteralNode,
    LifeStartNode, RunFuncNode, EventRestartNode,
    BinaryOpNode, UnaryOpNode,
)

BINARY_OPS = {'==', '!=', '<', '>', '<=', '>=', '&&', '||', '+', '-', '*', '/', '%'}
TYPE_KEYWORDS = {
    'any', 'bool', 'string', 'int', 'number',
    'float', 'double', 'array', 'object',
    'callable', 'void', 'null', 'mixed',
}


class Parser:
    def __init__(self, tokens: List[Token]):
        self.tokens = tokens
        self.pos = 0

    def current(self) -> Token:
        return self.tokens[self.pos] if self.pos < len(self.tokens) else Token('EOF', '')

    def peek(self, offset: int = 0) -> Token:
        idx = self.pos + offset
        return self.tokens[idx] if idx < len(self.tokens) else Token('EOF', '')

    def next(self):
        self.pos += 1

    def expect(self, type_: str, value: str = None) -> Token:
        tok = self.current()
        if tok.type != type_ or (value is not None and tok.value != value):
            raise SyntaxError(
                f"Unexpected token: {tok.type} {tok.value!r}, "
                f"expected {type_} {value!r}"
            )
        self.next()
        return tok

    def match(self, type_: str, value: str = None) -> bool:
        tok = self.current()
        if tok.type == type_ and (value is None or tok.value == value):
            self.next()
            return True
        return False

    def parse(self) -> ProgramNode:
        program = ProgramNode()
        while self.pos < len(self.tokens) - 1:
            tok = self.current()
            if tok.type == 'CONTROL' and tok.value == 'Regfunc':
                program.functions.append(self.parse_function_def())
            elif tok.type == 'CONTROL' and tok.value == 'LifeStart':
                program.life_start_expr = self.parse_life_start()
            else:
                self.next()
        return program

    # ---------- 函数定义 ----------
    def parse_function_def(self) -> FunctionDefNode:
        self.expect('CONTROL', 'Regfunc')
        signature = ''
        if self.match('SYMBOL', '<'):
            while self.pos < len(self.tokens) and not self.match('SYMBOL', '>'):
                tok = self.current()
                if tok.type == 'SYMBOL' and tok.value == '>':
                    break
                signature += tok.value
                self.next()

        self.expect('IDENTIFIER', 'Param')
        self.expect('SYMBOL', ':')
        params = []

        next_tok = self.current()
        if next_tok.type == 'IDENTIFIER':
            # 类型声明格式: Param:any&
            params.append(next_tok.value)
            self.next()
            self.expect('SYMBOL', '&')
        else:
            # 变量列表格式: Param:$a,$b&
            while True:
                tok = self.current()
                if tok.type == 'VARIABLE':
                    params.append(tok.value)
                    self.next()
                    if self.match('SYMBOL', ','):
                        continue
                    elif self.match('SYMBOL', '&'):
                        break
                    else:
                        raise SyntaxError("Expected ',' or '&' after parameter")
                elif tok.type == 'SYMBOL' and tok.value == '&':
                    self.next()
                    break
                else:
                    raise SyntaxError("Expected variable or &")

        body = self.parse_block()
        name = self._extract_function_name(body)
        if not name:
            raise SyntaxError("Function definition missing @SetCallBackName")

        func = FunctionDefNode()
        func.name = name
        func.signature = signature
        func.params = params
        func.param_count = len(params)
        func.body = body
        return func

    def _extract_function_name(self, block: BlockNode) -> Optional[str]:
        found = None
        for stmt in block.statements:
            if isinstance(stmt, CallNode) and stmt.name == 'SetCallBackName':
                if found is not None:
                    raise SyntaxError("Multiple @SetCallBackName declarations in one function")
                if stmt.args and isinstance(stmt.args[0], LiteralNode):
                    found = stmt.args[0].value
        return found

    # ---------- 块 ----------
    def parse_block(self) -> BlockNode:
        self.expect('SYMBOL', '{')
        block = BlockNode()
        while not self.match('SYMBOL', '}'):
            stmt = self.parse_statement()
            if stmt:
                block.statements.append(stmt)
        return block

    # ---------- 语句 ----------
    def parse_statement(self) -> Optional[ASTNode]:
        tok = self.current()

        # 空语句
        if self.match('SYMBOL', ';') or self.match('SYMBOL', '&'):
            return None

        # @Regfunc 动态定义
        if tok.type == 'CONTROL' and tok.value == 'Regfunc':
            return self.parse_function_def()

        # @EventRestart 语句
        if tok.type == 'CONTROL' and tok.value == 'EventRestart':
            self.next()
            self.expect('SYMBOL', '(')
            expr = self.parse_expression()
            self.expect('SYMBOL', ')')
            self.match('SYMBOL', ';') or self.match('SYMBOL', '&')
            return EventRestartNode(expr)

        # @pick 模式匹配
        if tok.type == 'CONTROL' and tok.value == 'pick':
            node = self.parse_pick()
            self.match('SYMBOL', ';') or self.match('SYMBOL', '&')
            return node

        # 控制流关键字
        if tok.type == 'KEYWORD':
            if tok.value == 'if':
                return self.parse_if()
            if tok.value == 'for':
                return self.parse_for()
            if tok.value == 'while':
                return self.parse_while()
            if tok.value == 'break':
                self.next()
                self.match('SYMBOL', ';') or self.match('SYMBOL', '&')
                return BreakNode()
            if tok.value == 'continue':
                self.next()
                self.match('SYMBOL', ';') or self.match('SYMBOL', '&')
                return ContinueNode()

        # 变量赋值
        if tok.type == 'VARIABLE':
            var_name = tok.value
            self.next()
            self.expect('SYMBOL', '=')
            expr = self.parse_expression()
            self.match('SYMBOL', ';') or self.match('SYMBOL', '&')
            return AssignNode(var_name, expr)

        # 控件调用
        if tok.type == 'CONTROL':
            name = tok.value
            self.next()
            args = self.parse_args()
            self.match('SYMBOL', ';') or self.match('SYMBOL', '&')
            if name == 'RunFunc':
                if len(args) < 1:
                    raise SyntaxError("RunFunc requires at least function name")
                func_name = args[0]
                return RunFuncNode(func_name, args[1:])
            return CallNode(name, args)

        raise SyntaxError(f"Unexpected token in statement: {tok.type} {tok.value!r}")

    # ---------- if ----------
    def parse_if(self) -> IfNode:
        self.expect('KEYWORD', 'if')
        self.expect('SYMBOL', '(')
        cond = self.parse_expression()
        self.expect('SYMBOL', ')')
        then_block = self.parse_block()
        else_block = None
        if self.match('KEYWORD', 'else'):
            if self.match('KEYWORD', 'if'):
                else_if = self.parse_if()
                wrapper = BlockNode()
                wrapper.statements.append(else_if)
                else_block = wrapper
            else:
                else_block = self.parse_block()
        node = IfNode()
        node.condition = cond
        node.then_branch = then_block
        node.else_branch = else_block
        return node

    # ---------- for ----------
    def parse_for(self) -> ForNode:
        self.expect('KEYWORD', 'for')
        self.expect('SYMBOL', '(')
        start = self.parse_expression()
        self.expect('SYMBOL', ':')
        end = self.parse_expression()
        step = None
        if self.match('SYMBOL', ':'):
            step = self.parse_expression()
        self.expect('SYMBOL', ')')
        body = self.parse_block()
        node = ForNode()
        node.start = start
        node.end = end
        node.step = step
        node.body = body
        return node

    # ---------- while ----------
    def parse_while(self) -> WhileNode:
        self.expect('KEYWORD', 'while')
        self.expect('SYMBOL', '(')
        cond = self.parse_expression()
        self.expect('SYMBOL', ')')
        body = self.parse_block()
        node = WhileNode()
        node.condition = cond
        node.body = body
        return node

    # ---------- LifeStart ----------
    def parse_life_start(self) -> LifeStartNode:
        self.expect('CONTROL', 'LifeStart')
        self.expect('SYMBOL', '(')
        expr = self.parse_expression()
        self.expect('SYMBOL', ')')
        return LifeStartNode(expr)

    # ---------- 参数列表 ----------
    def parse_args(self) -> List[ASTNode]:
        args = []
        if self.match('SYMBOL', '('):
            if not self.match('SYMBOL', ')'):
                args.append(self.parse_expression())
                while self.match('SYMBOL', ','):
                    args.append(self.parse_expression())
                self.expect('SYMBOL', ')')
        return args

    # ---------- 基础表达式 ----------
    def parse_primary(self) -> ASTNode:
        tok = self.current()

        # 字符串 / 数字
        if tok.type in ('STRING', 'NUMBER'):
            value = tok.value
            self.next()
            if tok.type == 'NUMBER':
                value = float(value) if '.' in value else int(value)
            return LiteralNode(value)

        # 布尔字面量
        if tok.type == 'KEYWORD' and tok.value in ('true', 'false'):
            value = tok.value == 'true'
            self.next()
            return LiteralNode(value)

        # 变量
        if tok.type == 'VARIABLE':
            name = tok.value
            self.next()
            return VariableNode(name)

        # 一元运算符 ! -
        if tok.type == 'SYMBOL' and tok.value in ('!', '-'):
            operator = tok.value
            self.next()
            operand = self.parse_expression()
            return UnaryOpNode(operator, operand)

        # @pick 表达式形式
        if tok.type == 'CONTROL' and tok.value == 'pick':
            return self.parse_pick()

        # @EventRestart 表达式
        if tok.type == 'CONTROL' and tok.value == 'EventRestart':
            self.next()
            self.expect('SYMBOL', '(')
            expr = self.parse_expression()
            self.expect('SYMBOL', ')')
            return EventRestartNode(expr)

        # 标识符 → 字符串字面量
        if tok.type == 'IDENTIFIER':
            value = tok.value
            self.next()
            return LiteralNode(value)

        # CONTROL 调用
        if tok.type == 'CONTROL':
            name = tok.value
            self.next()
            args = self.parse_args()
            if name == 'RunFunc':
                if len(args) < 1:
                    raise SyntaxError("RunFunc requires at least function name")
                func_name = args[0]
                return RunFuncNode(func_name, args[1:])
            return CallNode(name, args)

        # 括号表达式
        if tok.type == 'SYMBOL' and tok.value == '(':
            self.next()
            expr = self.parse_expression()
            self.expect('SYMBOL', ')')
            return expr

        # 数组字面量
        if tok.type == 'SYMBOL' and tok.value == '[':
            return self.parse_map_literal()

        raise SyntaxError(f"Unexpected token in expression: {tok.type} {tok.value!r}")

    # ---------- 表达式（支持二元运算） ----------
    def parse_expression(self) -> ASTNode:
        left = self.parse_primary()
        tok = self.current()
        if tok.type == 'SYMBOL' and tok.value in BINARY_OPS:
            operator = tok.value
            self.next()
            right = self.parse_expression()
            return BinaryOpNode(left, operator, right)
        return left

    # ---------- @pick ----------
    def parse_pick(self) -> ASTNode:
        self.expect('CONTROL', 'pick')
        self.expect('SYMBOL', '(')
        self.expect('IDENTIFIER', 'Param')
        self.expect('SYMBOL', ':')
        var_tok = self.current()
        if var_tok.type != 'VARIABLE':
            raise SyntaxError("Expected variable after Param:")
        var_name = var_tok.value
        self.next()
        self.expect('SYMBOL', ')')

        # 无 block → 表达式形式
        if not self.match('SYMBOL', '{'):
            return PickExprNode(var_name)

        # 有 block → 语句形式
        self.expect('KEYWORD', 'switch')
        self.expect('SYMBOL', '(')
        self.parse_expression()  # 忽略
        self.expect('SYMBOL', ')')
        self.expect('SYMBOL', '{')

        cases = {}
        default = None
        while not self.match('SYMBOL', '}'):
            if self.match('KEYWORD', 'case'):
                case_tok = self.current()
                if case_tok.type not in ('STRING', 'NUMBER'):
                    raise SyntaxError("Case value must be literal")
                case_val = case_tok.value
                self.next()
                self.expect('SYMBOL', ':')
                block = self.parse_block()
                cases[case_val] = block
            elif self.match('KEYWORD', 'default'):
                self.expect('SYMBOL', ':')
                default = self.parse_block()
            else:
                raise SyntaxError("Expected case or default")

        self.expect('SYMBOL', '}')
        node = PickNode()
        node.var_name = var_name
        node.cases = cases
        node.default = default
        return node

    # ---------- 数组字面量 ----------
    def parse_map_literal(self) -> MapLiteralNode:
        self.expect('SYMBOL', '[')
        pairs = []
        if self.match('SYMBOL', ']'):
            return MapLiteralNode(pairs)

        index = 0
        is_key_value = False

        first = self.parse_expression()
        if self.match('SYMBOL', '=>'):
            is_key_value = True
            val = self.parse_expression()
            pairs.append((first, val))
        else:
            pairs.append((LiteralNode(index), first))
            index += 1

        while self.match('SYMBOL', ','):
            if self.match('SYMBOL', ']'):
                return MapLiteralNode(pairs)
            if is_key_value:
                key = self.parse_expression()
                self.expect('SYMBOL', '=>')
                val = self.parse_expression()
                pairs.append((key, val))
            else:
                val = self.parse_expression()
                pairs.append((LiteralNode(index), val))
                index += 1

        self.expect('SYMBOL', ']')
        return MapLiteralNode(pairs)
