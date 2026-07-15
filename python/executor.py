"""CCHQCode Runtime - 执行器"""

from .types import ControlSignal, EventRestartSignal


class Executor:
    def __init__(self, env, program):
        self.env = env
        self.program = program

    def run(self):
        result = self.program.execute(self.env)
        if isinstance(result, ControlSignal) and result.type == 'return':
            return result.value
        return result
