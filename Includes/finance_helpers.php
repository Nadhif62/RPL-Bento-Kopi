<?php

function finance_table_exists(mysqli $conn, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $tableName = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$tableName}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function finance_refund_badge(string $status): string
{
    if ($status === 'approved') {
        return '<span class="badge badge-soft-success">Approved</span>';
    }

    if ($status === 'rejected') {
        return '<span class="badge badge-soft-danger">Rejected</span>';
    }

    return '<span class="badge badge-soft-warning">Pending</span>';
}

function finance_audit_badge(string $status, $difference): string
{
    $difference = (float)$difference;

    if ($status === 'active') {
        return '<span class="badge badge-soft-warning">Masih Aktif</span>';
    }

    if (abs($difference) > 0.009) {
        return '<span class="badge badge-soft-danger">Selisih</span>';
    }

    return '<span class="badge badge-soft-success">Sesuai</span>';
}

function finance_locked_badge(bool $locked): string
{
    if ($locked) {
        return '<span class="badge badge-soft-success">Locked</span>';
    }

    return '<span class="badge badge-soft-warning">Open</span>';
}
