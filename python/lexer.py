"""CCHQCode Runtime - 词法分析器"""

import re
from typing import List


class Token:
    def __init__(self, type_: str, value: str, line: int = 0, col: int = 0):
        self.type = type_
        self.value = value
        self.line = line
        self.col = col

    def __repr__(self):
        return f'Token({self.type}, {self.value!r})'


KEYWORDS = {
    'if', 'else', 'for', 'while', 'break', 'continue',
    'switch', 'case', 'default', 'return', 'true', 'false'
}

SYMBOLS_SINGLE = '{}();,:[]=+-*/%!<>'
SYMBOL_MAP = {
    '==': '==', '!=': '!=', '<=': '<=', '>=': '>=',
    '&&': '&&', '||': '||', '=>': '=>', '->': '->',
}


class Lexer:
    def __init__(self, source: str):
        self.source = source
        self.length = len(source)
        self.pos = 0
        self.line = 1
        self.col = 1

    def peek(self, offset: int = 0) -> str:
        p = self.pos + offset
        return self.source[p] if p < self.length else ''

    def tokenize(self) -> List[Token]:
        tokens = []
        while self.pos < self.length:
            ch = self.source[self.pos]

            # 空白
            if ch.isspace():
                if ch == '\n':
                    self.line += 1
                    self.col = 1
                else:
                    self.col += 1
                self.pos += 1
                continue

            # 注释 //
            if ch == '/' and self.peek(1) == '/':
                self.pos += 2
                while self.pos < self.length and self.source[self.pos] != '\n':
                    self.pos += 1
                continue

            # @ 控件
            if ch == '@':
                self.pos += 1
                name = ''
                while self.pos < self.length and re.match(r'[a-zA-Z0-9_:]', self.source[self.pos]):
                    name += self.source[self.pos]
                    self.pos += 1
                tokens.append(Token('CONTROL', name, self.line, self.col))
                self.col += len(name) + 1
                continue

            # 变量 $xxx
            if ch == '$':
                self.pos += 1
                name = ''
                while self.pos < self.length and re.match(r'[a-zA-Z0-9_]', self.source[self.pos]):
                    name += self.source[self.pos]
                    self.pos += 1
                tokens.append(Token('VARIABLE', name, self.line, self.col))
                self.col += len(name) + 1
                continue

            # 字符串
            if ch in ('"', "'"):
                quote = ch
                self.pos += 1
                s = ''
                while self.pos < self.length and self.source[self.pos] != quote:
                    if self.source[self.pos] == '\\':
                        self.pos += 1
                        s += self.source[self.pos] if self.pos < self.length else ''
                    else:
                        s += self.source[self.pos]
                    self.pos += 1
                self.pos += 1  # 跳过结束引号
                tokens.append(Token('STRING', s, self.line, self.col))
                self.col += len(s) + 2
                continue

            # 数字
            if ch.isdigit() or (ch == '-' and self.peek(1).isdigit()):
                num = ''
                if ch == '-':
                    num += '-'
                    self.pos += 1
                while self.pos < self.length and (self.source[self.pos].isdigit() or self.source[self.pos] == '.'):
                    num += self.source[self.pos]
                    self.pos += 1
                tokens.append(Token('NUMBER', num, self.line, self.col))
                self.col += len(num)
                continue

            # 复合符号（多字符）
            two_char = ch + self.peek(1)
            if two_char in SYMBOL_MAP:
                self.pos += 2
                tokens.append(Token('SYMBOL', SYMBOL_MAP[two_char], self.line, self.col))
                self.col += 2
                continue

            # 单字符符号（& 需要特殊处理，因为 & 也可能是 && 的一部分）
            if ch == '&':
                # 前面已经处理了 &&，这里处理单独的 &
                self.pos += 1
                tokens.append(Token('SYMBOL', '&', self.line, self.col))
                self.col += 1
                continue

            if ch == '|':
                # 前面已经处理了 ||
                self.pos += 1
                self.col += 1
                continue

            # 其他单符号
            if ch in SYMBOLS_SINGLE:
                tokens.append(Token('SYMBOL', ch, self.line, self.col))
                self.pos += 1
                self.col += 1
                continue

            # 关键字或标识符
            m = re.match(r'[a-zA-Z_][a-zA-Z0-9_]*', self.source[self.pos:])
            if m:
                word = m.group(0)
                tok_type = 'KEYWORD' if word in KEYWORDS else 'IDENTIFIER'
                tokens.append(Token(tok_type, word, self.line, self.col))
                self.pos += len(word)
                self.col += len(word)
                continue

            # 忽略其他
            self.pos += 1
            self.col += 1

        tokens.append(Token('EOF', '', self.line, self.col))
        return tokens
