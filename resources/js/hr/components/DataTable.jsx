import { useState } from 'react';
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from './ui/table';
import { cn } from '../lib/utils';

/**
 * @param {Object} props
 * @param {{ key: string, label: string, sortable?: boolean, className?: string, headClassName?: string, render?: (row: any) => React.ReactNode }[]} props.columns
 * @param {any[]} props.data
 * @param {string} [props.emptyMessage]
 * @param {string} [props.sortKey]
 * @param {'asc' | 'desc'} [props.sortDirection]
 * @param {(key: string) => void} [props.onSort]
 * @param {(row: any) => void} [props.onRowClick]
 * @param {(row: any) => string} [props.rowKey]
 */
export default function DataTable({
    columns,
    data,
    emptyMessage = 'No records found.',
    sortKey,
    sortDirection,
    onSort,
    onRowClick,
    rowKey,
}) {
    const [localSortKey, setLocalSortKey] = useState(null);
    const [localSortDir, setLocalSortDir] = useState('asc');

    const activeSortKey = sortKey ?? localSortKey;
    const activeSortDir = sortDirection ?? localSortDir;

    function handleSort(key) {
        if (onSort) {
            onSort(key);
            return;
        }
        if (localSortKey === key) {
            setLocalSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setLocalSortKey(key);
            setLocalSortDir('asc');
        }
    }

    const sortedData =
        !onSort && localSortKey
            ? [...data].sort((a, b) => {
                  const aVal = a[localSortKey] ?? '';
                  const bVal = b[localSortKey] ?? '';
                  const cmp =
                      typeof aVal === 'string'
                          ? aVal.localeCompare(bVal)
                          : aVal - bVal;
                  return localSortDir === 'asc' ? cmp : -cmp;
              })
            : data;

    function SortIcon({ columnKey }) {
        if (activeSortKey !== columnKey) {
            return <ArrowUpDown className="ml-1 h-3.5 w-3.5 text-slate-300 transition-colors group-hover:text-slate-500" />;
        }
        return activeSortDir === 'asc' ? (
            <ArrowUp className="ml-1 h-3.5 w-3.5 text-indigo-600" />
        ) : (
            <ArrowDown className="ml-1 h-3.5 w-3.5 text-indigo-600" />
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    {columns.map((col) => (
                        <TableHead
                            key={col.key}
                            aria-sort={
                                col.sortable
                                    ? activeSortKey === col.key
                                        ? activeSortDir === 'asc'
                                            ? 'ascending'
                                            : 'descending'
                                        : 'none'
                                    : undefined
                            }
                            className={cn(
                                col.sortable && 'group cursor-pointer select-none transition-colors hover:bg-slate-100',
                                col.headClassName,
                                col.className
                            )}
                            onClick={col.sortable ? () => handleSort(col.key) : undefined}
                        >
                            <span className="inline-flex items-center">
                                {col.label}
                                {col.sortable && <SortIcon columnKey={col.key} />}
                            </span>
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {sortedData.length === 0 ? (
                    <TableRow>
                        <TableCell
                            colSpan={columns.length}
                            className="h-24 text-center text-slate-400"
                        >
                            {emptyMessage}
                        </TableCell>
                    </TableRow>
                ) : (
                    sortedData.map((row, idx) => (
                        <TableRow
                            key={rowKey ? rowKey(row) : (row.id ?? idx)}
                            onClick={onRowClick ? () => onRowClick(row) : undefined}
                            className={onRowClick ? 'cursor-pointer' : undefined}
                        >
                            {columns.map((col) => (
                                <TableCell
                                    key={col.key}
                                    className={col.className}
                                >
                                    {col.render ? col.render(row) : row[col.key]}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))
                )}
            </TableBody>
        </Table>
    );
}
