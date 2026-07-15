import { Token } from './types';
import {
  ASTNode, ProgramNode, FunctionDefNode, BlockNode,
  LiteralNode, VariableNode, AssignNode, CallNode,
  IfNode, ForNode, WhileNode, BreakNode, ContinueNode,
  PickNode, PickExprNode, MapLiteralNode,
  LifeStartNode, RunFuncNode, EventRestartNode, JsonPathNode,
  BinaryOpNode, UnaryOpNode
} from './ast';

export class Parser {
  private tokens: Token[];
  private pos: number = 0;

  constructor(tokens: Token[]) {
    this.tokens = tokens;
  }

  private current(): Token { return this.tokens[this.pos] ?? { type: 'EOF', value: null, line: 0, col: 0 }; }
  private peek(offset: number = 0): Token {
    const idx = this.pos + offset;
    return idx < this.tokens.length ? this.tokens[idx] : { type: 'EOF', value: null, line: 0, col: 0 };
  }
  private next(): void { this.pos++; }

  private expect(type: string, value?: string | null): Token {
    const token = this.current();
    if (token.type !== type || (value !== undefined && token.value !== value)) {
      throw new Error(`Unexpected token: ${token.type} ${token.value}, expected ${type} ${value}`);
    }
    this.next();
    return token;
  }

  private match(type: string, value?: string | null): boolean {
    const token = this.current();
    if (token.type === type && (value === undefined || token.value === value)) {
      this.next();
      return true;
    }
    return false;
  }

  parse(): ProgramNode {
    const program = new ProgramNode();
    while (this.pos < this.tokens.length - 1) {
      const token = this.current();
      if (token.type === 'CONTROL' && token.value === 'Regfunc') {
        program.functions.push(this.parseFunctionDef());
      } else if (token.type === 'CONTROL' && token.value === 'LifeStart') {
        program.lifeStartExpr = this.parseLifeStart();
      } else {
        this.next();
      }
    }
    return program;
  }

  private parseFunctionDef(): FunctionDefNode {
    this.expect('CONTROL', 'Regfunc');
    let signature = '';
    if (this.match('SYMBOL', '<')) {
      while (this.pos < this.tokens.length && !this.match('SYMBOL', '>')) {
        const tok = this.current();
        if (tok.type === 'SYMBOL' && tok.value === '>') break;
        signature += tok.value ?? '';
        this.next();
      }
    }
    this.expect('IDENTIFIER', 'Param');
    this.expect('SYMBOL', ':');
    const params: string[] = [];
    const nextTok = this.current();
    if (nextTok.type === 'IDENTIFIER') {
      // 类型声明格式: Param:any&
      params.push(nextTok.value ?? '');
      this.next();
      this.expect('SYMBOL', '&');
    } else {
      // 变量列表格式: Param:$a,$b&
      while (true) {
        const tok = this.current();
        if (tok.type === 'VARIABLE') {
          params.push(tok.value ?? '');
          this.next();
          if (this.match('SYMBOL', ',')) continue;
          else if (this.match('SYMBOL', '&')) break;
          else throw new Error("Expected ',' or '&' after parameter");
        } else if (tok.type === 'SYMBOL' && tok.value === '&') {
          this.next();
          break;
        } else {
          throw new Error('Expected variable or &');
        }
      }
    }
    const body = this.parseBlock();
    const name = this.extractFunctionName(body);
    if (!name) throw new Error('Function definition missing @SetCallBackName');

    const func = new FunctionDefNode();
    func.name = name;
    func.signature = signature;
    func.params = params;
    func.paramCount = params.length;
    func.body = body;
    return func;
  }

  private extractFunctionName(block: BlockNode): string | null {
    let found: string | null = null;
    for (const stmt of block.statements) {
      if (stmt instanceof CallNode && stmt.name === 'SetCallBackName') {
        if (found !== null) {
          throw new Error('Multiple @SetCallBackName declarations in one function');
        }
        if (stmt.args.length > 0 && stmt.args[0] instanceof LiteralNode) {
          found = stmt.args[0].value;
        }
      }
    }
    return found;
  }

  private parseBlock(): BlockNode {
    this.expect('SYMBOL', '{');
    const block = new BlockNode();
    while (!this.match('SYMBOL', '}')) {
      const stmt = this.parseStatement();
      if (stmt) block.statements.push(stmt);
    }
    return block;
  }

  private parseStatement(): ASTNode | null {
    const token = this.current();

    // 空语句
    if (this.match('SYMBOL', ';') || this.match('SYMBOL', '&')) return null;

    // @Regfunc 函数定义（支持在 block 内动态注册）
    if (token.type === 'CONTROL' && token.value === 'Regfunc') {
      return this.parseFunctionDef();
    }

    // @EventRestart 特殊处理
    if (token.type === 'CONTROL' && token.value === 'EventRestart') {
      this.next();
      this.expect('SYMBOL', '(');
      const expr = this.parseExpression();
      this.expect('SYMBOL', ')');
      this.match('SYMBOL', ';') || this.match('SYMBOL', '&');
      return new EventRestartNode(expr);
    }

    // @pick 模式匹配
    if (token.type === 'CONTROL' && token.value === 'pick') {
      const node = this.parsePick();
      this.match('SYMBOL', ';') || this.match('SYMBOL', '&');
      return node;
    }

    // 控制流关键字
    if (token.type === 'KEYWORD') {
      switch (token.value) {
        case 'if': return this.parseIf();
        case 'for': return this.parseFor();
        case 'while': return this.parseWhile();
        case 'break':
          this.next();
          this.match('SYMBOL', ';') || this.match('SYMBOL', '&');
          return new BreakNode();
        case 'continue':
          this.next();
          this.match('SYMBOL', ';') || this.match('SYMBOL', '&');
          return new ContinueNode();
        default: break;
      }
    }

    // 变量赋值
    if (token.type === 'VARIABLE') {
      const varName = token.value ?? '';
      this.next();
      this.expect('SYMBOL', '=');
      const expr = this.parseExpression();
      this.match('SYMBOL', ';') || this.match('SYMBOL', '&');
      return new AssignNode(varName, expr);
    }

    // 控件调用
    if (token.type === 'CONTROL') {
      const name = token.value ?? '';
      this.next();
      const args = this.parseArgs();
      this.match('SYMBOL', ';') || this.match('SYMBOL', '&');
      if (name === 'RunFunc') {
        if (args.length < 1) throw new Error('RunFunc requires at least function name');
        const funcName = args[0];
        return new RunFuncNode(funcName, args.slice(1));
      }
      return new CallNode(name, args);
    }

    throw new Error(`Unexpected token in statement: ${token.type} ${token.value}`);
  }

  private parseIf(): IfNode {
    this.expect('KEYWORD', 'if');
    this.expect('SYMBOL', '(');
    const cond = this.parseExpression();
    this.expect('SYMBOL', ')');
    const thenBlock = this.parseBlock();
    let elseBlock: ASTNode | null = null;
    if (this.match('KEYWORD', 'else')) {
      if (this.match('KEYWORD', 'if')) {
        const elseIf = this.parseIf();
        const wrapper = new BlockNode();
        wrapper.statements.push(elseIf);
        elseBlock = wrapper;
      } else {
        elseBlock = this.parseBlock();
      }
    }
    const node = new IfNode();
    node.condition = cond;
    node.thenBranch = thenBlock;
    node.elseBranch = elseBlock;
    return node;
  }

  private parseFor(): ForNode {
    this.expect('KEYWORD', 'for');
    this.expect('SYMBOL', '(');
    const start = this.parseExpression();
    this.expect('SYMBOL', ':');
    const end = this.parseExpression();
    let step: ASTNode | null = null;
    if (this.match('SYMBOL', ':')) {
      step = this.parseExpression();
    }
    this.expect('SYMBOL', ')');
    const body = this.parseBlock();
    const node = new ForNode();
    node.start = start;
    node.end = end;
    node.step = step;
    node.body = body;
    return node;
  }

  private parseWhile(): WhileNode {
    this.expect('KEYWORD', 'while');
    this.expect('SYMBOL', '(');
    const cond = this.parseExpression();
    this.expect('SYMBOL', ')');
    const body = this.parseBlock();
    const node = new WhileNode();
    node.condition = cond;
    node.body = body;
    return node;
  }

  private parseLifeStart(): LifeStartNode {
    this.expect('CONTROL', 'LifeStart');
    this.expect('SYMBOL', '(');
    const expr = this.parseExpression();
    this.expect('SYMBOL', ')');
    return new LifeStartNode(expr);
  }

  private parseArgs(): ASTNode[] {
    const args: ASTNode[] = [];
    if (this.match('SYMBOL', '(')) {
      if (!this.match('SYMBOL', ')')) {
        args.push(this.parseExpression());
        while (this.match('SYMBOL', ',')) {
          args.push(this.parseExpression());
        }
        this.expect('SYMBOL', ')');
      }
    }
    return args;
  }

  private parsePrimary(): ASTNode {
    const token = this.current();

    if (token.type === 'STRING' || token.type === 'NUMBER') {
      let value: any = token.value;
      this.next();
      if (token.type === 'NUMBER') {
        value = (value as string).includes('.') ? parseFloat(value) : parseInt(value, 10);
      }
      return new LiteralNode(value);
    }

    // 布尔字面量
    if (token.type === 'KEYWORD' && (token.value === 'true' || token.value === 'false')) {
      const value = token.value === 'true';
      this.next();
      return new LiteralNode(value);
    }

    if (token.type === 'VARIABLE') {
      const name = token.value ?? '';
      this.next();
      return new VariableNode(name);
    }

    // 一元运算符 ! 和 -
    if (token.type === 'SYMBOL' && (token.value === '!' || token.value === '-')) {
      const operator = token.value!;
      this.next();
      const operand = this.parseExpression();
      return new UnaryOpNode(operator, operand);
    }

    // @pick 表达式形式（必须在 CONTROL 分支之前）
    if (token.type === 'CONTROL' && token.value === 'pick') {
      return this.parsePick();
    }

    // @EventRestart
    if (token.type === 'CONTROL' && token.value === 'EventRestart') {
      this.next();
      this.expect('SYMBOL', '(');
      const expr = this.parseExpression();
      this.expect('SYMBOL', ')');
      return new EventRestartNode(expr);
    }

    // 标识符作为字符串字面量
    if (token.type === 'IDENTIFIER') {
      const value = token.value ?? '';
      this.next();
      // 处理 JSON->"path" 表达式
      if (value === 'JSON' && this.current().type === 'SYMBOL' && this.current().value === '->') {
        this.next();
        const pathTok = this.current();
        if (pathTok.type !== 'STRING') throw new Error('Expected string after JSON->');
        const path = pathTok.value ?? '';
        this.next();
        return new JsonPathNode(path);
      }
      return new LiteralNode(value);
    }

    // CONTROL 调用
    if (token.type === 'CONTROL') {
      const name = token.value ?? '';
      this.next();
      const args = this.parseArgs();
      if (name === 'RunFunc') {
        if (args.length < 1) throw new Error('RunFunc requires at least function name');
        const funcName = args[0];
        return new RunFuncNode(funcName, args.slice(1));
      }
      return new CallNode(name, args);
    }

    // 括号表达式
    if (token.type === 'SYMBOL' && token.value === '(') {
      this.next();
      const expr = this.parseExpression();
      this.expect('SYMBOL', ')');
      return expr;
    }

    // 数组字面量
    if (token.type === 'SYMBOL' && token.value === '[') {
      return this.parseMapLiteral();
    }

    throw new Error(`Unexpected token in expression: ${token.type} ${token.value}`);
  }

  private parseExpression(): ASTNode {
    const left = this.parsePrimary();
    const token = this.current();
    const binaryOps = ['==', '!=', '<', '>', '<=', '>=', '&&', '||', '+', '-', '*', '/', '%'];
    if (token.type === 'SYMBOL' && token.value !== null && binaryOps.includes(token.value)) {
      const operator = token.value;
      this.next();
      const right = this.parseExpression();
      return new BinaryOpNode(left, operator, right);
    }
    return left;
  }

  private parsePick(): ASTNode {
    this.expect('CONTROL', 'pick');
    this.expect('SYMBOL', '(');
    this.expect('IDENTIFIER', 'Param');
    this.expect('SYMBOL', ':');
    const varToken = this.current();
    if (varToken.type !== 'VARIABLE') throw new Error('Expected variable after Param:');
    const varName = varToken.value ?? '';
    this.next();
    this.expect('SYMBOL', ')');

    // 无 block → 表达式形式
    if (!this.match('SYMBOL', '{')) {
      return new PickExprNode(varName);
    }

    // 有 block → 语句形式
    this.expect('KEYWORD', 'switch');
    this.expect('SYMBOL', '(');
    this.parseExpression(); // 可忽略
    this.expect('SYMBOL', ')');
    this.expect('SYMBOL', '{');
    const cases: Record<string, BlockNode> = {};
    let default_: BlockNode | null = null;

    while (!this.match('SYMBOL', '}')) {
      if (this.match('KEYWORD', 'case')) {
        const caseTok = this.current();
        if (caseTok.type !== 'STRING' && caseTok.type !== 'NUMBER') {
          throw new Error('Case value must be literal');
        }
        const caseVal = caseTok.value ?? '';
        this.next();
        this.expect('SYMBOL', ':');
        const block = this.parseBlock();
        cases[caseVal] = block;
      } else if (this.match('KEYWORD', 'default')) {
        this.expect('SYMBOL', ':');
        default_ = this.parseBlock();
      } else {
        throw new Error('Expected case or default');
      }
    }
    this.expect('SYMBOL', '}');

    const node = new PickNode();
    node.varName = varName;
    node.cases = cases;
    node.default_ = default_;
    return node;
  }

  private parseMapLiteral(): MapLiteralNode {
    this.expect('SYMBOL', '[');
    const pairs: [ASTNode, ASTNode][] = [];
    if (this.match('SYMBOL', ']')) {
      return new MapLiteralNode(pairs);
    }

    let index = 0;
    let isKeyValue = false;

    const first = this.parseExpression();
    if (this.match('SYMBOL', '=>')) {
      isKeyValue = true;
      const val = this.parseExpression();
      pairs.push([first, val]);
    } else {
      pairs.push([new LiteralNode(index++), first]);
    }

    while (this.match('SYMBOL', ',')) {
      if (this.match('SYMBOL', ']')) {
        return new MapLiteralNode(pairs);
      }
      if (isKeyValue) {
        const key = this.parseExpression();
        this.expect('SYMBOL', '=>');
        const val = this.parseExpression();
        pairs.push([key, val]);
      } else {
        const val = this.parseExpression();
        pairs.push([new LiteralNode(index++), val]);
      }
    }

    this.expect('SYMBOL', ']');
    return new MapLiteralNode(pairs);
  }
}
