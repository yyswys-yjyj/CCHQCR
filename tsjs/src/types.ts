// ===== 词法分析类型 =====
export type TokenType =
  | 'CONTROL' | 'VARIABLE' | 'STRING' | 'NUMBER'
  | 'SYMBOL' | 'KEYWORD' | 'IDENTIFIER' | 'EOF';

export interface Token {
  type: TokenType;
  value: string | null;
  line: number;
  col: number;
}

// ===== 控制信号 =====
export class ControlSignal {
  type: 'break' | 'continue' | 'return';
  value: any;
  constructor(type: 'break' | 'continue' | 'return', value?: any) {
    this.type = type;
    this.value = value;
  }
}

// ===== 事件重启异常 =====
export class EventRestartSignal {
  newPayload: any;
  constructor(payload: any) {
    this.newPayload = payload;
  }
}
