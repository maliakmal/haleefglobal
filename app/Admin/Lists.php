<?php
declare(strict_types=1);

namespace Haleef\Admin;

use PDO;

final class Lists
{
    public static function query(): string
    {
        return is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,160) : '';
    }

    public static function status(array $options): string
    {
        return is_string($_GET['status'] ?? null) && isset($options[$_GET['status']]) ? $_GET['status'] : '';
    }

    public static function like(string $value): string
    {
        return '%'.strtr($value,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
    }

    public static function select(PDO $pdo, string $sql, array $fields, string $sort, string $statusField = '', array $statuses = [], ?int $limit = null): \PDOStatement
    {
        $args = [];
        if (($search = self::query()) !== '') {
            $sql .= ' AND (' . implode(' OR ',array_map(static fn($field)=>"$field LIKE ? ESCAPE '!'", $fields)) . ')';
            $args = array_fill(0,count($fields),self::like($search));
        }
        if ($statusField !== '' && ($status = self::status($statuses)) !== '') {
            $sql .= " AND $statusField=?";
            $args[] = $status;
        }
        $q = $pdo->prepare($sql.' ORDER BY '.$sort.($limit && !self::exporting() ? ' LIMIT '.$limit : ''));
        $q->execute($args);
        return $q;
    }

    public static function exporting(): bool { return ($_GET['format'] ?? '') === 'csv'; }

    public static function csv(string $name, array $headings, iterable $rows): never
    {
        header('Cache-Control: private, no-store');
        header('Content-Type: text/csv; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="'.$name.'-'.date('Y-m-d').'.csv"');
        $out = fopen('php://output','wb');
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,$headings,',','"','',"\r\n");
        foreach ($rows as $row) {
            $cells = array_map(static function ($value): string {
                $cell = (string)$value;
                return preg_match('/^(?:[\t\r\n]|[\p{Z}\s]*[=+@\-＝＋＠－])/u',$cell) ? "'".$cell : $cell;
            },$row);
            fputcsv($out,$cells,',','"','',"\r\n");
        }
        fclose($out);
        exit;
    }

    public static function rows(\PDOStatement $query): iterable
    {
        while ($row = $query->fetch(PDO::FETCH_NUM)) yield $row;
    }

    public static function filter(string $path, array $statuses = [], string $placeholder = 'Search this list'): void
    {
        $q = self::query();
        $status = self::status($statuses);
        $query = http_build_query(array_filter(['format'=>'csv','q'=>$q,'status'=>$status],static fn($x)=>$x!==''));
        ?>
        <form method="get" action="<?= e($path) ?>" class="admin-list-filter" role="search">
            <label>Search<input name="q" type="search" maxlength="160" value="<?= e($q) ?>" placeholder="<?= e($placeholder) ?>"></label>
            <?php if ($statuses): ?><label>Status<select name="status"><option value="">All statuses</option>
                <?php foreach ($statuses as $value=>$label): ?><option value="<?= e((string)$value) ?>" <?= $status===(string)$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select></label><?php endif; ?>
            <button type="submit">Filter</button>
            <?php if ($q !== '' || $status !== ''): ?><a href="<?= e($path) ?>">Clear</a><?php endif; ?>
            <a href="<?= e($path.'?'.$query) ?>">Download CSV</a>
        </form>
        <?php
    }

    public static function searchRows(array $rows, array $fields): array
    {
        $q = self::query();
        if ($q === '') return $rows;
        return array_values(array_filter($rows,static function (array $row) use ($fields,$q): bool {
            foreach ($fields as $field) if (mb_stripos((string)($row[$field] ?? ''),$q)!==false) return true;
            return false;
        }));
    }
}
