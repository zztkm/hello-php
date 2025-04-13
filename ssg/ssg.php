<?php
// 厳密な型付けを有効にする
// https://www.php.net/manual/ja/language.types.declarations.php#language.types.declarations.strict
declare(strict_types=1);

// 出力先ディレクトの定数
define('OUTPUT_DIR', '_build');

/**
 * Markdown ファイルパス (SplFileInfo オブジェクト) の配列を受け取り、
 * HTML に変換するクラス。
 * 変換はジェネレーター (convertToHtml) を使用して行うため、ファイルへの書き込みは利用者側で行う。
 */
final readonly class HtmlGenerator
{
    // NOTE: SplFileInfo をうまく使う: https://www.php.net/manual/ja/class.splfileinfo.php

    /**
     * SplFileInfo オブジェクトの配列
     * @var array<int, SplFileInfo> $files
     */
    private array $files;

    function __construct(array $files)
    {
        $this->files = $files;
    }

    /**
     * HTML に変換するジェネレーター
     * 
     * @return Generator<SplFileInfo, string> 変換された HTML のジェネレーター (key: HTML の元になったファイルの SplFileInfo, value: HTML)
     * @throws RuntimeException ファイルの読み込みに失敗した場合
     */
    function genHtml(): Generator
    {
        foreach ($this->files as $file) {
            // TODO(zztkm): Markdown から HTML への変換処理を実装する
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                throw new RuntimeException("ファイルの読み込みに失敗しました: " . $file->getPathname());
            }

            yield $file => $this->convertToHtml($file);
        }
    }

    private function convertToHtml(SplFileInfo $fileInfo): string
    {
        // refs: https://www.php.net/manual/ja/class.splfileobject.php
        $fileObj = $fileInfo->openFile('r');

        // 書き込み用の content 変数
        $content = '';
        while (!$fileObj->eof()) {
            $line = $fileObj->fgets();

            if (empty(trim($line))) {
                continue;
            }

            // TODO(zztkm): 一旦雑変換ロジックを作ったのであとで修正する
            // TODO(zztkm): コードブロックの変換処理を実装する
            // ヘッダー判定
            if (preg_match('/^#/', $line)) {
                // ヘッダーのレベルを取得
                $level = strlen($line) - strlen(ltrim($line, '#'));
                // ヘッダーの内容を取得 (# と ヘッダーの間には 1 つ空白がある前提)
                $header = substr($line, $level + 1);
                // content に追加
                $content .= "<h" . $level . ">" . htmlspecialchars($header) . "</h" . $level . ">";
            } else {
                // その他の行は <p> タグで囲む
                $content .= "<p>" . htmlspecialchars($line) . "</p>";
            }
        }
        return $content;
    }
}

/**
 * HTML ファイルのパスを生成する関数
 * 
 * @param SplFileInfo $file Markdown ファイルの SplFileInfo オブジェクト
 * @param string $buildDirPath 出力先のディレクトリパス
 * @param string $rootDirPath プロジェクトのルートディレクトリパス
 * @return string HTML ファイルのフルパス
 */
function getHtmlFilePath(SplFileInfo $baseFile, string $buildDirPath, string $rootDirPath): string
{
    // $file->getPathname() から $rootDirPath を取り除く
    $relativePath = str_replace($rootDirPath, '', $baseFile->getPathname());
    // $relativePath の先頭に DIRECTORY_SEPARATOR が付いている場合は取り除く
    if (str_starts_with($relativePath, DIRECTORY_SEPARATOR)) {
        $relativePath = substr($relativePath, 1);
    }
    // 拡張子を .html に変更
    $relativePath = preg_replace('/\.[^.]+$/', '.html', $relativePath);
    return $buildDirPath . DIRECTORY_SEPARATOR . $relativePath;
}


/**
 * 指定されたディレクトリ以下の .md ファイルのパスを再帰的に検索し、
 * パスの配列を返す。
 *
 * @param string $rootPath 検索を開始するディレクトリのパス
 * @return array<int, SplFileInfo> 見つかった .md ファイルのフルパスの配列
 */
function findMarkdownFilePathsRecursive(string $rootPath): array
{
    $markdownFilePaths = [];

    // ディレクトリが存在し、読み取り可能か確認
    if (!is_dir($rootPath) || !is_readable($rootPath)) {
        // エラーメッセージを出力しても良いが、ここでは単に空配列を返す
        // error_log("検索ディレクトリが見つからないか、読み込めません: " . $rootPath);
        return [];
    }

    try {
        $directoryIterator = new RecursiveDirectoryIterator(
            $rootPath,
            RecursiveDirectoryIterator::SKIP_DOTS // '.' や '..' をスキップ
        );

        $fileIterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::LEAVES_ONLY,
            // エラー発生時 (例: 権限のないディレクトリ) に例外をスローする
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($fileIterator as $fileInfo) {
            // $fileInfo は SplFileInfo オブジェクト
            // https://www.php.net/manual/en/class.splfileinfo.php

            // $rootPath . DIRECTORY_SEPARATOR . OUTPUT_DIR で始まる場合はスキップ
            if (str_starts_with($fileInfo->getPathname(), $rootPath . DIRECTORY_SEPARATOR . OUTPUT_DIR)) {
                continue;
            }

            // ファイルであり、拡張子が 'md' かどうかを確認 (大文字小文字を区別しない)
            if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'md') {
                // ファイルのフルパスを配列に追加
                $markdownFilePaths[] = $fileInfo;
            }
        }
    } catch (UnexpectedValueException $e) {
        // RecursiveDirectoryIterator でアクセス権限などの問題が発生した場合
        error_log("ディレクトリ探索中にエラーが発生しました (" . $rootPath . "): " . $e->getMessage());
        // エラーが発生しても、それまでに見つかったパスを返すか、空を返すか選択
        // return []; // エラー時は空を返す場合
    } catch (Exception $e) {
        // その他の予期せぬエラー
        error_log("予期せぬエラーが発生しました (" . $rootPath . "): " . $e->getMessage());
        // return []; // エラー時は空を返す場合
    }

    return $markdownFilePaths;
}

/**
 * ディレクトリとその中身を再帰的に強制削除する。
 *
 * @param string $dirPath 削除するディレクトリのパス。
 * @return bool 成功した場合は true、失敗した場合は false。
 */
function forceDeleteDirectory(string $dirPath): bool
{
    if (!is_dir($dirPath)) {
        return false;
    }

    try {
        $iterator = new DirectoryIterator($dirPath);

        foreach ($iterator as $item) {
            // '.' と '..' エントリはスキップ
            if ($item->isDot()) {
                continue;
            }

            $itemPath = $item->getPathname();

            if ($item->isDir()) {
                if (!forceDeleteDirectory($itemPath)) {
                    return false;
                }
            } elseif ($item->isFile() || $item->isLink()) {
                if (!unlink($itemPath)) {
                    return false;
                }
            }
        }

        if (!rmdir($dirPath)) {
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("Exception while deleting directory '{$dirPath}': " . $e->getMessage());
        return false;
    }
}

// CLI 引数でディレクトリを指定
// 指定されたディレクトリのパスは DIRECTORY_SEPARATOR で区切られた形式で渡されることを期待する
$rootPath = $argv[1] ?? '.'; // デフォルトはカレントディレクトリ

$htmlGenerator = new HtmlGenerator(findMarkdownFilePathsRecursive($rootPath));

// 出力先のディレクトリを作成
$buildDirPath = $rootPath . DIRECTORY_SEPARATOR . OUTPUT_DIR;
// 毎回クリーンビルド
if (!forceDeleteDirectory($buildDirPath)) {
    echo "Failed to delete directory: " . $buildDirPath . "\n";
    exit(1);
}
mkdir($buildDirPath, 0777, true);

foreach ($htmlGenerator->genHtml() as $file => $html) {

    $htmlFilePath = getHtmlFilePath($file, $buildDirPath, $rootPath);
    // file_put_contents はファイルの書き込みしかできないため、
    // $htmlFilePath の親ディレクトリが存在しない場合は作成する
    $htmlDirPath = dirname($htmlFilePath);

    // NOTE: is_dir は「ファイルが存在して、かつそれがディレクトリであれば true、それ以外の場合は false を返します。」という仕様で、
    // それを利用して、ディレクトリが存在しない場合は作成するようにするテクニックがあるっぽい。
    // https://www.php.net/manual/ja/function.is-dir.php
    if (!is_dir($htmlDirPath)) {
        mkdir($htmlDirPath, 0777, true);
    }
    file_put_contents($htmlFilePath, $html);
    echo "Converted: " . $file->getPathname() . " -> " . $htmlFilePath . "\n";
}

echo "Completed.\n";
