import { Token, TokenType } from './types';

/**
 * 词法分析器
 */
export class Lexer {
  private source: string;
  private length: number;
  private pos: number = 0;
  private line: number = 1;
  private col: number = 1;

  constructor(source: string) {
    this.source = source;
    this.length = source.length;
  }

  tokenize(): Token[] {
    const tokens: Token[] = [];
    while (this.pos < this.length) {
      const ch = this.source[this.pos];

      // 跳过空白
      if (/\s/.test(ch)) {
        if (ch === '\n') { this.line++; this.col = 1; } else { this.col++; }
        this.pos++;
        continue;
      }

      // 注释 //
      if (ch === '/' && this.peek(1) === '/') {
        this.pos += 2;
        while (this.pos < this.length && this.source[this.pos] !== '\n') {
          this.pos++;
        }
        continue;
      }

      // @ 控件
      if (ch === '@') {
        this.pos++;
        let name = '';
        while (this.pos < this.length && /[a-zA-Z0-9_:]/.test(this.source[this.pos])) {
          name += this.source[this.pos];
          this.pos++;
        }
        tokens.push({ type: 'CONTROL', value: name, line: this.line, col: this.col });
        this.col += name.length + 1;
        continue;
      }

      // 变量 $xxx
      if (ch === '$') {
        this.pos++;
        let name = '';
        while (this.pos < this.length && /[a-zA-Z0-9_]/.test(this.source[this.pos])) {
          name += this.source[this.pos];
          this.pos++;
        }
        tokens.push({ type: 'VARIABLE', value: name, line: this.line, col: this.col });
        this.col += name.length + 1;
        continue;
      }

      // 字符串
      if (ch === '"' || ch === "'") {
        const quote = ch;
        this.pos++;
        let str = '';
        while (this.pos < this.length && this.source[this.pos] !== quote) {
          if (this.source[this.pos] === '\\') {
            this.pos++;
            str += this.source[this.pos] ?? '';
          } else {
            str += this.source[this.pos];
          }
          this.pos++;
        }
        this.pos++; // 跳过结束引号
        tokens.push({ type: 'STRING', value: str, line: this.line, col: this.col });
        this.col += str.length + 2;
        continue;
      }

      // 数字
      if (/[0-9]/.test(ch) || (ch === '-' && this.pos + 1 < this.length && /[0-9]/.test(this.source[this.pos + 1]))) {
        let num = '';
        if (ch === '-') { num += '-'; this.pos++; }
        while (this.pos < this.length && (/[0-9.]/.test(this.source[this.pos]))) {
          num += this.source[this.pos];
          this.pos++;
        }
        tokens.push({ type: 'NUMBER', value: num, line: this.line, col: this.col });
        this.col += num.length;
        continue;
      }

      // 复合符号处理
      if (ch === '=') {
        this.pos++;
        if (this.pos < this.length && this.source[this.pos] === '=') {
          this.pos++;
          tokens.push({ type: 'SYMBOL', value: '==', line: this.line, col: this.col });
          this.col += 2;
        } else if (this.pos < this.length && this.source[this.pos] === '>') {
          this.pos++;
          tokens.push({ type: 'SYMBOL', value: '=>', line: this.line, col: this.col });
          this.col += 2;
        } else {
          tokens.push({ type: 'SYMBOL', value: '=', line: this.line, col: this.col });
          this.col++;
        }
        continue;
      }

      if (ch === '!') {
        this.pos++;
        if (this.pos < this.length && this.source[this.pos] === '=') {
          this.pos++;
          tokens.push({ type: 'SYMBOL', value: '!=', line: this.line, col: this.col });
          this.col += 2;
        } else {
          tokens.push({ type: 'SYMBOL', value: '!', line: this.line, col: this.col });
          this.col++;
        }
        continue;
      }

      if (ch === '<') {
        this.pos++;
        if (this.pos < this.length && this.source[this.pos] === '=') {
          this.pos++;
          tokens.push({ type: 'SYMBOL', value: '<=', line: this.line, col: this.col });
          this.col += 2;
        } else {
          tokens.push({ type: 'SYMBOL', value: '<', line: this.line, col: this.col });
          this.col++;
        }
        continue;
      }

      if (ch === '>') {
        this.pos++;
        if (this.pos < this.length && this.source[this.pos] === '=') {
          this.pos++;
          tokens.push({ type: 'SYMBOL', value: '>=', line: this.line, col: this.col });
          this.col += 2;
        } else {
          tokens.push({ type: 'SYMBOL', value: '>', line: this.line, col: this.col });
          this.col++;
        }
        continue;
      }

      if (ch === '&') {
        this.pos++;
        if (this.pos < this.length && this.source[this.pos] === '&') {
          this.pos++;
          tokens.push({ type: 'SYMBOL', value: '&&', line: this.line, col: this.col });
          this.col += 2;
        } else {
          tokens.push({ type: 'SYMBOL', value: '&', line: this.line, col: this.col });
          this.col++;
        }
        continue;
      }

      if (ch === '|') {
        this.pos++;
        if (this.pos < this.length && this.source[this.pos] === '|') {
          this.pos++;
          tokens.push({ type: 'SYMBOL', value: '||', line: this.line, col: this.col });
          this.col += 2;
        } else {
          this.col++;
        }
        continue;
      }

      // -> 复合符号（JSON 路径指示器）
      if (ch === '-' && this.pos + 1 < this.length && this.source[this.pos + 1] === '>') {
        this.pos += 2;
        tokens.push({ type: 'SYMBOL', value: '->', line: this.line, col: this.col });
        this.col += 2;
        continue;
      }

      // 其他单个符号
      if ('{}();,:[]=+-*/%'.includes(ch)) {
        tokens.push({ type: 'SYMBOL', value: ch, line: this.line, col: this.col });
        this.pos++;
        this.col++;
        continue;
      }

      // 关键字或标识符
      const match = this.source.slice(this.pos).match(/^[a-zA-Z_][a-zA-Z0-9_]*/);
      if (match) {
        const word = match[0];
        const keywords = ['if', 'else', 'for', 'while', 'break', 'continue', 'switch', 'case', 'default', 'return', 'true', 'false'];
        if (keywords.includes(word)) {
          tokens.push({ type: 'KEYWORD', value: word, line: this.line, col: this.col });
        } else {
          tokens.push({ type: 'IDENTIFIER', value: word, line: this.line, col: this.col });
        }
        this.pos += word.length;
        this.col += word.length;
        continue;
      }

      // 忽略其他字符
      this.pos++;
      this.col++;
    }
    tokens.push({ type: 'EOF', value: null, line: this.line, col: this.col });
    return tokens;
  }

  private peek(offset: number): string | null {
    const p = this.pos + offset;
    return p < this.length ? this.source[p] : null;
  }
}
