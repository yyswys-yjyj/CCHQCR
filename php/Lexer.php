<?php
// Lexer.php - 词法分析器（支持复合符号）

class Lexer {
    private $source;
    private $length;
    private $pos;
    private $line;
    private $col;

    public function __construct($source) {
        $this->source = $source;
        $this->length = strlen($source);
        $this->pos = 0;
        $this->line = 1;
        $this->col = 1;
    }

    public function tokenize() {
        $tokens = [];
        while ($this->pos < $this->length) {
            $ch = $this->source[$this->pos];

            // 跳过空白
            if (ctype_space($ch)) {
                if ($ch === "\n") { $this->line++; $this->col = 1; } else { $this->col++; }
                $this->pos++;
                continue;
            }

            // 注释 // ...
            if ($ch === '/' && $this->peek(1) === '/') {
                $this->pos += 2;
                while ($this->pos < $this->length && $this->source[$this->pos] !== "\n") {
                    $this->pos++;
                }
                continue;
            }

            // @ 控件
            if ($ch === '@') {
                $this->pos++;
                $name = '';
                while ($this->pos < $this->length && preg_match('/[a-zA-Z0-9_:]/', $this->source[$this->pos])) {
                    $name .= $this->source[$this->pos];
                    $this->pos++;
                }
                $tokens[] = ['type' => 'CONTROL', 'value' => $name, 'line' => $this->line, 'col' => $this->col];
                $this->col += strlen($name) + 1;
                continue;
            }

            // 变量 $xxx
            if ($ch === '$') {
                $this->pos++;
                $name = '';
                while ($this->pos < $this->length && preg_match('/[a-zA-Z0-9_]/', $this->source[$this->pos])) {
                    $name .= $this->source[$this->pos];
                    $this->pos++;
                }
                $tokens[] = ['type' => 'VARIABLE', 'value' => $name, 'line' => $this->line, 'col' => $this->col];
                $this->col += strlen($name) + 1;
                continue;
            }

            // 字符串
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $this->pos++;
                $str = '';
                while ($this->pos < $this->length && $this->source[$this->pos] !== $quote) {
                    if ($this->source[$this->pos] === '\\') {
                        $this->pos++;
                        $str .= $this->source[$this->pos] ?? '';
                    } else {
                        $str .= $this->source[$this->pos];
                    }
                    $this->pos++;
                }
                $this->pos++; // 跳过结束引号
                $tokens[] = ['type' => 'STRING', 'value' => $str, 'line' => $this->line, 'col' => $this->col];
                $this->col += strlen($str) + 2;
                continue;
            }

            // 数字
            if (is_numeric($ch) || ($ch === '-' && $this->pos + 1 < $this->length && is_numeric($this->source[$this->pos + 1]))) {
                $num = '';
                if ($ch === '-') { $num .= '-'; $this->pos++; }
                while ($this->pos < $this->length && (is_numeric($this->source[$this->pos]) || $this->source[$this->pos] === '.')) {
                    $num .= $this->source[$this->pos];
                    $this->pos++;
                }
                $tokens[] = ['type' => 'NUMBER', 'value' => $num, 'line' => $this->line, 'col' => $this->col];
                $this->col += strlen($num);
                continue;
            }

            // ---- 复合符号处理 ----
            if ($ch === '=') {
                $this->pos++;
                if ($this->pos < $this->length && $this->source[$this->pos] === '=') {
                    $this->pos++;
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '==', 'line' => $this->line, 'col' => $this->col];
                    $this->col += 2;
                } elseif ($this->pos < $this->length && $this->source[$this->pos] === '>') {
                    $this->pos++;
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '=>', 'line' => $this->line, 'col' => $this->col];
                    $this->col += 2;
                } else {
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '=', 'line' => $this->line, 'col' => $this->col];
                    $this->col++;
                }
                continue;
            }
            if ($ch === '!') {
                $this->pos++;
                if ($this->pos < $this->length && $this->source[$this->pos] === '=') {
                    $this->pos++;
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '!=', 'line' => $this->line, 'col' => $this->col];
                    $this->col += 2;
                } else {
                    // 单独 ! 忽略或作为错误
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '!', 'line' => $this->line, 'col' => $this->col];
                    $this->col++;
                }
                continue;
            }
            if ($ch === '<') {
                $this->pos++;
                if ($this->pos < $this->length && $this->source[$this->pos] === '=') {
                    $this->pos++;
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '<=', 'line' => $this->line, 'col' => $this->col];
                    $this->col += 2;
                } else {
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '<', 'line' => $this->line, 'col' => $this->col];
                    $this->col++;
                }
                continue;
            }
            if ($ch === '>') {
                $this->pos++;
                if ($this->pos < $this->length && $this->source[$this->pos] === '=') {
                    $this->pos++;
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '>=', 'line' => $this->line, 'col' => $this->col];
                    $this->col += 2;
                } else {
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '>', 'line' => $this->line, 'col' => $this->col];
                    $this->col++;
                }
                continue;
            }
            if ($ch === '&') {
                $this->pos++;
                if ($this->pos < $this->length && $this->source[$this->pos] === '&') {
                    $this->pos++;
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '&&', 'line' => $this->line, 'col' => $this->col];
                    $this->col += 2;
                } else {
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '&', 'line' => $this->line, 'col' => $this->col];
                    $this->col++;
                }
                continue;
            }
            if ($ch === '|') {
                $this->pos++;
                if ($this->pos < $this->length && $this->source[$this->pos] === '|') {
                    $this->pos++;
                    $tokens[] = ['type' => 'SYMBOL', 'value' => '||', 'line' => $this->line, 'col' => $this->col];
                    $this->col += 2;
                } else {
                    // 单竖线忽略
                    $this->col++;
                }
                continue;
            }

            // -> 符合符号（JSON 路径指示器）
            if ($ch === '-' && $this->pos + 1 < $this->length && $this->source[$this->pos + 1] === '>') {
                $this->pos += 2;
                $tokens[] = ['type' => 'SYMBOL', 'value' => '->', 'line' => $this->line, 'col' => $this->col];
                $this->col += 2;
                continue;
            }

            // 其他单个符号：{}();,:[] 
            if (strpos('{}();:,&=+-*/%[]', $ch) !== false) {
                $tokens[] = ['type' => 'SYMBOL', 'value' => $ch, 'line' => $this->line, 'col' => $this->col];
                $this->pos++;
                $this->col++;
                continue;
            }

            // 关键字或标识符
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*/', substr($this->source, $this->pos), $match)) {
                $word = $match[0];
                $keywords = ['if','else','for','while','break','continue','switch','case','default','return','true','false'];
                if (in_array($word, $keywords)) {
                    $tokens[] = ['type' => 'KEYWORD', 'value' => $word, 'line' => $this->line, 'col' => $this->col];
                } else {
                    $tokens[] = ['type' => 'IDENTIFIER', 'value' => $word, 'line' => $this->line, 'col' => $this->col];
                }
                $this->pos += strlen($word);
                $this->col += strlen($word);
                continue;
            }

            // 忽略其他字符
            $this->pos++;
            $this->col++;
        }
        $tokens[] = ['type' => 'EOF', 'value' => null, 'line' => $this->line, 'col' => $this->col];
        return $tokens;
    }

    private function peek($offset) {
        $pos = $this->pos + $offset;
        return ($pos < $this->length) ? $this->source[$pos] : null;
    }
}