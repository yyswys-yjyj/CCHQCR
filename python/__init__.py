"""CCHQCode Runtime - Python 入口

用法:
    from cchqcr import run_cchq, create_runtime

    # 方式一：一次性执行
    result = run_cchq('''
        @Regfunc<>Param:$payload&{
            @SetCallBackName("Main");
            @ReturnToBot("Hello");
        }
        @LifeStart(@RunFunc(Main, $payload))
    ''', {"payload": "World"})
    print(result)  # "Hello"

    # 方式二：可复用 Runtime
    rt = create_runtime({"payload": "World"})
    rt.register_control("Double", lambda x: x * 2)
    result = rt.execute("...")
"""

from .lexer import Lexer
from .parser import Parser
from .environment import Environment
from .executor import Executor
from .builtins import register_builtins


def run_cchq(script: str, context=None):
    """执行 CCHQ 脚本，返回执行结果"""
    if context is None:
        context = {}
    env = Environment(context)
    register_builtins(env)

    lexer = Lexer(script)
    tokens = lexer.tokenize()

    parser = Parser(tokens)
    program = parser.parse()

    executor = Executor(env, program)
    return executor.run()


def create_runtime(context=None):
    """创建可复用的 Runtime 实例"""
    if context is None:
        context = {}
    env = Environment(context)
    register_builtins(env)

    class Runtime:
        def execute(self, script: str):
            lexer = Lexer(script)
            tokens = lexer.tokenize()
            parser = Parser(tokens)
            program = parser.parse()
            executor = Executor(env, program)
            return executor.run()

        def register_control(self, name: str, callable_):
            env.register_control(name, callable_)

        def get_environment(self):
            return env

    return Runtime()
