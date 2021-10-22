# 验证器

## 简介
- 兼容 Hyperf/Laravel Validation 规则
- 提升约 500 倍性能
- 验证器可多次复用不同数据
- 规则可全局复用
- 智能合并规则

## 安装

### 环境要求
- PHP >= 8.0   
- mbstring 扩展   
- ctype 扩展   

### 安装命令

```bash
composer require kkgroup/validation
```

## 待办

- 暂不支持转义 `.`, `*` 等关键符 (好做但是暂时还没需求)
- 规则没有全部适配
- 多语言支持 (或许该库只应该实现核心部分, 其它的可以在上层做)
