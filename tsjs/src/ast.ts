import { ControlSignal, EventRestartSignal } from './types';
import { Environment } from './environment';

// ===== 基类 =====
export abstract class ASTNode {
  abstract execute(env: Environment): any;
}
export abstract class ExpressionNode extends ASTNode {}

// ===== 程序根节点 =====
export class ProgramNode extends ASTNode {
  functions: FunctionDefNode[] = [];
  lifeStartExpr: ASTNode | null = null;

  execute(env: Environment): any {
    for (const func of this.functions) {
      env.registerFunction(func.name, func, func.paramCount);
    }
    if (this.lifeStartExpr) {
      return this.lifeStartExpr.execute(env);
    }
    return null;
  }
}

// ===== 函数定义节点 =====
export class FunctionDefNode extends ASTNode {
  name: string = '';
  signature: string = '';
  params: string[] = [];
  paramCount: number = 0;
  body: BlockNode | null = null;

  execute(env: Environment): any {
    env.registerFunction(this.name, this, this.paramCount);
    return null;
  }
}

// ===== 块节点 =====
export class BlockNode extends ASTNode {
  statements: ASTNode[] = [];

  execute(env: Environment): any {
    let returnVal: any = null;
    for (const stmt of this.statements) {
      const result = stmt.execute(env);
      if (result instanceof ControlSignal) {
        return result;
      }
      returnVal = result;
    }
    return returnVal;
  }
}

// ===== 字面量 =====
export class LiteralNode extends ExpressionNode {
  value: any;
  constructor(value: any) { super(); this.value = value; }
  execute(env: Environment): any { return this.value; }
}

// ===== 一元运算 =====
export class UnaryOpNode extends ExpressionNode {
  operator: string;
  operand: ASTNode;
  constructor(operator: string, operand: ASTNode) { super(); this.operator = operator; this.operand = operand; }
  execute(env: Environment): any {
    const val = this.operand.execute(env);
    switch (this.operator) {
      case '!': return !val;
      case '-': return -val;
      default: throw new Error(`Unknown unary operator: ${this.operator}`);
    }
  }
}

// ===== 变量 =====
export class VariableNode extends ExpressionNode {
  name: string;
  constructor(name: string) { super(); this.name = name; }
  execute(env: Environment): any { return env.getVariable(this.name); }
}

// ===== 赋值 =====
export class AssignNode extends ExpressionNode {
  varName: string;
  expr: ASTNode;
  constructor(varName: string, expr: ASTNode) { super(); this.varName = varName; this.expr = expr; }
  execute(env: Environment): any {
    const value = this.expr.execute(env);
    env.setVariable(this.varName, value);
    return value;
  }
}

// ===== 二元运算 =====
export class BinaryOpNode extends ExpressionNode {
  left: ASTNode;
  operator: string;
  right: ASTNode;
  constructor(left: ASTNode, operator: string, right: ASTNode) {
    super(); this.left = left; this.operator = operator; this.right = right;
  }
  execute(env: Environment): any {
    const l = this.left.execute(env);
    const r = this.right.execute(env);
    switch (this.operator) {
      case '==': return l == r;
      case '!=': return l != r;
      case '<':  return l < r;
      case '>':  return l > r;
      case '<=': return l <= r;
      case '>=': return l >= r;
      case '&&': return l && r;
      case '||': return l || r;
      case '+':  return l + r;
      case '-':  return l - r;
      case '*':  return l * r;
      case '/':  return l / r;
      case '%':  return l % r;
      default:   throw new Error(`Unknown operator: ${this.operator}`);
    }
  }
}

// ===== 调用节点（控件/函数调用） =====
export class CallNode extends ExpressionNode {
  name: string;
  args: ASTNode[];
  constructor(name: string, args: ASTNode[]) { super(); this.name = name; this.args = args; }
  execute(env: Environment): any {
    const argValues = this.args.map(a => a.execute(env));
    if (env.hasControl(this.name)) {
      return env.callControl(this.name, argValues);
    }
    if (env.hasFunction(this.name, argValues.length)) {
      return env.callFunction(this.name, argValues);
    }
    throw new Error(`Undefined callable: ${this.name}`);
  }
}

// ===== If 节点 =====
export class IfNode extends ASTNode {
  condition: ASTNode | null = null;
  thenBranch: ASTNode | null = null;
  elseBranch: ASTNode | null = null;
  execute(env: Environment): any {
    const cond = this.condition!.execute(env);
    if (cond) {
      return this.thenBranch!.execute(env);
    } else if (this.elseBranch) {
      return this.elseBranch.execute(env);
    }
    return null;
  }
}

// ===== For 循环 =====
export class ForNode extends ASTNode {
  start: ASTNode | null = null;
  end: ASTNode | null = null;
  step: ASTNode | null = null;
  body: ASTNode | null = null;
  loopVar: string = 'i';

  execute(env: Environment): any {
    const startVal = this.start!.execute(env);
    const endVal = this.end!.execute(env);
    const stepVal = this.step ? this.step.execute(env) : 1;
    for (let i = startVal; i <= endVal; i += stepVal) {
      env.pushScope();
      env.setVariable(this.loopVar, i);
      const result = this.body!.execute(env);
      env.popScope();
      if (result instanceof ControlSignal) {
        if (result.type === 'break') break;
        if (result.type === 'continue') continue;
        if (result.type === 'return') return result;
      }
    }
    return null;
  }
}

// ===== While 循环 =====
export class WhileNode extends ASTNode {
  condition: ASTNode | null = null;
  body: ASTNode | null = null;
  execute(env: Environment): any {
    while (this.condition!.execute(env)) {
      env.pushScope();
      const result = this.body!.execute(env);
      env.popScope();
      if (result instanceof ControlSignal) {
        if (result.type === 'break') break;
        if (result.type === 'continue') continue;
        if (result.type === 'return') return result;
      }
    }
    return null;
  }
}

// ===== Break / Continue =====
export class BreakNode extends ASTNode {
  execute(env: Environment): any { return new ControlSignal('break'); }
}
export class ContinueNode extends ASTNode {
  execute(env: Environment): any { return new ControlSignal('continue'); }
}

// ===== @pick 模式匹配（语句形式） =====
export class PickNode extends ASTNode {
  varName: string = '';
  cases: Record<string, BlockNode> = {};
  default_: BlockNode | null = null;

  execute(env: Environment): any {
    const value = env.getVariable(this.varName);
    for (const [caseVal, block] of Object.entries(this.cases)) {
      if (value == caseVal) {
        const result = block.execute(env);
        if (result instanceof ControlSignal && (result.type === 'break' || result.type === 'continue')) {
          return null;
        }
        return result;
      }
    }
    if (this.default_) {
      const result = this.default_.execute(env);
      if (result instanceof ControlSignal && (result.type === 'break' || result.type === 'continue')) {
        return null;
      }
      return result;
    }
    return null;
  }
}

// ===== @pick 表达式形式 =====
export class PickExprNode extends ExpressionNode {
  varName: string;
  constructor(varName: string) { super(); this.varName = varName; }
  execute(env: Environment): any {
    const param = env.getVariable('Param');
    if (typeof param === 'object' && param !== null && this.varName in param) {
      return param[this.varName];
    }
    return param;
  }
}

// ===== 数组字面量 =====
export class MapLiteralNode extends ExpressionNode {
  pairs: [ASTNode, ASTNode][];
  constructor(pairs: [ASTNode, ASTNode][]) { super(); this.pairs = pairs; }
  execute(env: Environment): any {
    const result: Record<string, any> = {};
    for (const [keyNode, valNode] of this.pairs) {
      const key = keyNode.execute(env);
      const value = valNode.execute(env);
      result[key] = value;
    }
    return result;
  }
}

// ===== @LifeStart =====
export class LifeStartNode extends ASTNode {
  expr: ASTNode;
  constructor(expr: ASTNode) { super(); this.expr = expr; }
  execute(env: Environment): any { return this.expr.execute(env); }
}

// ===== @RunFunc =====
export class RunFuncNode extends ExpressionNode {
  funcName: ASTNode;
  args: ASTNode[];
  constructor(funcName: ASTNode, args: ASTNode[]) { super(); this.funcName = funcName; this.args = args; }
  execute(env: Environment): any {
    let fName: any = this.funcName;
    if (fName instanceof LiteralNode) {
      fName = fName.value;
    } else {
      fName = fName.execute(env);
    }
    const argValues = this.args.map(a => a.execute(env));

    // 无参数时继承当前 Param
    if (argValues.length === 0) {
      const param = env.getVariable('Param');
      if (typeof param === 'object' && param !== null && 'quantity' in param) {
        argValues.push(...param.args);
      } else {
        argValues.push(param);
      }
    }

    const result = env.callFunction(fName, argValues);
    env.setRunFuncResult(result);
    if (result instanceof ControlSignal && result.type === 'return') {
      env.setRunFuncResult(result.value);
      return result.value;
    }
    return result;
  }
}

// ===== @EventRestart =====
export class EventRestartNode extends ASTNode {
  newPayloadExpr: ASTNode;
  constructor(expr: ASTNode) { super(); this.newPayloadExpr = expr; }
  execute(env: Environment): any {
    const newPayload = this.newPayloadExpr.execute(env);
    throw new EventRestartSignal(newPayload);
  }
}
