import { Environment } from './environment';
import { ProgramNode, BlockNode, FunctionDefNode, LifeStartNode } from './ast';
import { ControlSignal } from './types';

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
    const result = this.program.execute(this.environment);
    if (result instanceof ControlSignal && result.type === 'return') {
      return result.value;
    }
    return result;
  }
}
