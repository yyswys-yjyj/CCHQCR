import { Environment } from './environment';
import { ProgramNode, BlockNode, FunctionDefNode, LifeStartNode } from './ast';
import { ControlSignal, EventRestartSignal } from './types';

/**
 * 执行器
 */
export class Executor {
  private environment: Environment;
  private program: ProgramNode;

  constructor(env: Environment, program: ProgramNode) {
    this.environment = env;
    this.program = program;
  }

  run(): any {
    while (true) {
      try {
        const result = this.program.execute(this.environment);
        if (result instanceof ControlSignal && result.type === 'return') {
          return result.value;
        }
        return result;
      } catch (err: any) {
        if (err instanceof EventRestartSignal) {
          this.environment.setContext(err.newPayload);
          this.environment.resetScopes();
          // 继续循环，重新执行
        } else {
          throw err;
        }
      }
    }
  }
}
