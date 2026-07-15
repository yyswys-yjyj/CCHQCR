"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.EventRestartNode = exports.JsonPathNode = exports.RunFuncNode = exports.LifeStartNode = exports.MapLiteralNode = exports.PickExprNode = exports.PickNode = exports.ContinueNode = exports.BreakNode = exports.WhileNode = exports.ForNode = exports.IfNode = exports.CallNode = exports.BinaryOpNode = exports.AssignNode = exports.VariableNode = exports.UnaryOpNode = exports.LiteralNode = exports.BlockNode = exports.FunctionDefNode = exports.ProgramNode = exports.ExpressionNode = exports.ASTNode = void 0;
const types_1 = require("./types");
// ===== 基类 =====
class ASTNode {
}
exports.ASTNode = ASTNode;
class ExpressionNode extends ASTNode {
}
exports.ExpressionNode = ExpressionNode;
// ===== 程序根节点 =====
class ProgramNode extends ASTNode {
    constructor() {
        super(...arguments);
        this.functions = [];
        this.lifeStartExpr = null;
    }
    execute(env) {
        for (const func of this.functions) {
            env.registerFunction(func.name, func, func.paramCount);
        }
        if (this.lifeStartExpr) {
            return this.lifeStartExpr.execute(env);
        }
        return null;
    }
}
exports.ProgramNode = ProgramNode;
// ===== 函数定义节点 =====
class FunctionDefNode extends ASTNode {
    constructor() {
        super(...arguments);
        this.name = '';
        this.signature = '';
        this.params = [];
        this.paramCount = 0;
        this.body = null;
    }
    execute(env) {
        env.registerFunction(this.name, this, this.paramCount);
        return null;
    }
}
exports.FunctionDefNode = FunctionDefNode;
// ===== 块节点 =====
class BlockNode extends ASTNode {
    constructor() {
        super(...arguments);
        this.statements = [];
    }
    execute(env) {
        let returnVal = null;
        for (const stmt of this.statements) {
            const result = stmt.execute(env);
            if (result instanceof types_1.ControlSignal) {
                return result;
            }
            returnVal = result;
        }
        return returnVal;
    }
}
exports.BlockNode = BlockNode;
// ===== 字面量 =====
class LiteralNode extends ExpressionNode {
    constructor(value) { super(); this.value = value; }
    execute(env) { return this.value; }
}
exports.LiteralNode = LiteralNode;
// ===== 一元运算 =====
class UnaryOpNode extends ExpressionNode {
    constructor(operator, operand) { super(); this.operator = operator; this.operand = operand; }
    execute(env) {
        const val = this.operand.execute(env);
        switch (this.operator) {
            case '!': return !val;
            case '-': return -val;
            default: throw new Error(`Unknown unary operator: ${this.operator}`);
        }
    }
}
exports.UnaryOpNode = UnaryOpNode;
// ===== 变量 =====
class VariableNode extends ExpressionNode {
    constructor(name) { super(); this.name = name; }
    execute(env) { return env.getVariable(this.name); }
}
exports.VariableNode = VariableNode;
// ===== 赋值 =====
class AssignNode extends ExpressionNode {
    constructor(varName, expr) { super(); this.varName = varName; this.expr = expr; }
    execute(env) {
        const value = this.expr.execute(env);
        env.setVariable(this.varName, value);
        return value;
    }
}
exports.AssignNode = AssignNode;
// ===== 二元运算 =====
class BinaryOpNode extends ExpressionNode {
    constructor(left, operator, right) {
        super();
        this.left = left;
        this.operator = operator;
        this.right = right;
    }
    execute(env) {
        const l = this.left.execute(env);
        const r = this.right.execute(env);
        switch (this.operator) {
            case '==': return l == r;
            case '!=': return l != r;
            case '<': return l < r;
            case '>': return l > r;
            case '<=': return l <= r;
            case '>=': return l >= r;
            case '&&': return l && r;
            case '||': return l || r;
            case '+': return l + r;
            case '-': return l - r;
            case '*': return l * r;
            case '/': return l / r;
            case '%': return l % r;
            default: throw new Error(`Unknown operator: ${this.operator}`);
        }
    }
}
exports.BinaryOpNode = BinaryOpNode;
// ===== 调用节点（控件/函数调用） =====
class CallNode extends ExpressionNode {
    constructor(name, args) { super(); this.name = name; this.args = args; }
    execute(env) {
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
exports.CallNode = CallNode;
// ===== If 节点 =====
class IfNode extends ASTNode {
    constructor() {
        super(...arguments);
        this.condition = null;
        this.thenBranch = null;
        this.elseBranch = null;
    }
    execute(env) {
        const cond = this.condition.execute(env);
        if (cond) {
            return this.thenBranch.execute(env);
        }
        else if (this.elseBranch) {
            return this.elseBranch.execute(env);
        }
        return null;
    }
}
exports.IfNode = IfNode;
// ===== For 循环 =====
class ForNode extends ASTNode {
    constructor() {
        super(...arguments);
        this.start = null;
        this.end = null;
        this.step = null;
        this.body = null;
        this.loopVar = 'i';
    }
    execute(env) {
        const startVal = this.start.execute(env);
        const endVal = this.end.execute(env);
        const stepVal = this.step ? this.step.execute(env) : 1;
        for (let i = startVal; i <= endVal; i += stepVal) {
            env.pushScope();
            env.setVariable(this.loopVar, i);
            const result = this.body.execute(env);
            env.popScope();
            if (result instanceof types_1.ControlSignal) {
                if (result.type === 'break')
                    break;
                if (result.type === 'continue')
                    continue;
                if (result.type === 'return')
                    return result;
            }
        }
        return null;
    }
}
exports.ForNode = ForNode;
// ===== While 循环 =====
class WhileNode extends ASTNode {
    constructor() {
        super(...arguments);
        this.condition = null;
        this.body = null;
    }
    execute(env) {
        while (this.condition.execute(env)) {
            env.pushScope();
            const result = this.body.execute(env);
            env.popScope();
            if (result instanceof types_1.ControlSignal) {
                if (result.type === 'break')
                    break;
                if (result.type === 'continue')
                    continue;
                if (result.type === 'return')
                    return result;
            }
        }
        return null;
    }
}
exports.WhileNode = WhileNode;
// ===== Break / Continue =====
class BreakNode extends ASTNode {
    execute(env) { return new types_1.ControlSignal('break'); }
}
exports.BreakNode = BreakNode;
class ContinueNode extends ASTNode {
    execute(env) { return new types_1.ControlSignal('continue'); }
}
exports.ContinueNode = ContinueNode;
// ===== @pick 模式匹配（语句形式） =====
class PickNode extends ASTNode {
    constructor() {
        super(...arguments);
        this.varName = '';
        this.cases = {};
        this.default_ = null;
    }
    execute(env) {
        const value = env.getVariable(this.varName);
        for (const [caseVal, block] of Object.entries(this.cases)) {
            if (value == caseVal) {
                const result = block.execute(env);
                if (result instanceof types_1.ControlSignal && (result.type === 'break' || result.type === 'continue')) {
                    return null;
                }
                return result;
            }
        }
        if (this.default_) {
            const result = this.default_.execute(env);
            if (result instanceof types_1.ControlSignal && (result.type === 'break' || result.type === 'continue')) {
                return null;
            }
            return result;
        }
        return null;
    }
}
exports.PickNode = PickNode;
// ===== @pick 表达式形式 =====
class PickExprNode extends ExpressionNode {
    constructor(varName) { super(); this.varName = varName; }
    execute(env) {
        const param = env.getVariable('Param');
        if (typeof param === 'object' && param !== null && this.varName in param) {
            return param[this.varName];
        }
        return param;
    }
}
exports.PickExprNode = PickExprNode;
// ===== 数组字面量 =====
class MapLiteralNode extends ExpressionNode {
    constructor(pairs) { super(); this.pairs = pairs; }
    execute(env) {
        const result = {};
        for (const [keyNode, valNode] of this.pairs) {
            const key = keyNode.execute(env);
            const value = valNode.execute(env);
            result[key] = value;
        }
        return result;
    }
}
exports.MapLiteralNode = MapLiteralNode;
// ===== @LifeStart =====
class LifeStartNode extends ASTNode {
    constructor(expr) { super(); this.expr = expr; }
    execute(env) { return this.expr.execute(env); }
}
exports.LifeStartNode = LifeStartNode;
// ===== @RunFunc =====
class RunFuncNode extends ExpressionNode {
    constructor(funcName, args) { super(); this.funcName = funcName; this.args = args; }
    execute(env) {
        let fName = this.funcName;
        if (fName instanceof LiteralNode) {
            fName = fName.value;
        }
        else {
            fName = fName.execute(env);
        }
        const argValues = this.args.map(a => a.execute(env));
        // 无参数时继承当前 Param
        if (argValues.length === 0) {
            const param = env.getVariable('Param');
            if (typeof param === 'object' && param !== null && 'quantity' in param) {
                argValues.push(...param.args);
            }
            else {
                argValues.push(param);
            }
        }
        const result = env.callFunction(fName, argValues);
        env.setRunFuncResult(result);
        if (result instanceof types_1.ControlSignal && result.type === 'return') {
            env.setRunFuncResult(result.value);
            return result.value;
        }
        return result;
    }
}
exports.RunFuncNode = RunFuncNode;
// ===== JSON 路径节点 JSON->"path" =====
class JsonPathNode extends ExpressionNode {
    constructor(path) { super(); this.path = path; }
    execute(env) {
        return { __json_path__: true, path: this.path };
    }
}
exports.JsonPathNode = JsonPathNode;
// ===== @EventRestart =====
class EventRestartNode extends ASTNode {
    constructor(expr) { super(); this.newPayloadExpr = expr; }
    execute(env) {
        const newPayload = this.newPayloadExpr.execute(env);
        throw new types_1.EventRestartSignal(newPayload);
    }
}
exports.EventRestartNode = EventRestartNode;
