# Hello PHP SSG

PHP で静的サイトジェネレーターを作成してみました。

## 使い方

```bash
php ssg.php examples

# 出力例 (Windows)
Converted: examples\index.md -> examples\_build\index.html
Converted: examples\posts\index.md -> examples\_build\posts\index.html
Converted: examples\posts\post1.md -> examples\_build\posts\post1.html
Completed.
```

を実行すると、`examples/` ディレクトリ内の Markdown ファイルを HTML に変換し、`examples/_build` ディレクトリに出力します。
