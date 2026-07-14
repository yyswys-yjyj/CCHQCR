"""CCHQCode Runtime - 类型定义"""

from typing import Any, Optional


class ControlSignal:
    """控制信号（break / continue / return）"""
    def __init__(self, type_: str, value: Any = None):
        self.type = type_
        self.value = value


class EventRestartSignal(Exception):
    """事件重启信号"""
    def __init__(self, new_payload: Any):
        super().__init__("EventRestart triggered")
        self.new_payload = new_payload
