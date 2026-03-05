import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import 'package:mobile_ver/features/todo/models/todo_item.dart';

class TodoTaskTile extends StatelessWidget {
  final TodoItem item;
  final ValueChanged<TodoItem> onToggleCompleted;
  final ValueChanged<TodoItem> onDelete;

  const TodoTaskTile({
    super.key,
    required this.item,
    required this.onToggleCompleted,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final due = item.dueDate;
    final dueLabel = due == null ? null : DateFormat('dd MMM yyyy, HH:mm', 'id_ID').format(due);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Checkbox(
            value: item.completed,
            onChanged: (_) => onToggleCompleted(item),
            visualDensity: VisualDensity.compact,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
          ),
          const SizedBox(width: 2),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: TextStyle(
                    color: const Color(0xFF0F172A),
                    fontWeight: FontWeight.w700,
                    decoration: item.completed ? TextDecoration.lineThrough : TextDecoration.none,
                  ),
                ),
                if (item.description.trim().isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    item.description,
                    style: const TextStyle(
                      color: Color(0xFF64748B),
                      fontSize: 12,
                    ),
                  ),
                ],
                if (dueLabel != null) ...[
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEEF2FF),
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Text(
                      dueLabel,
                      style: const TextStyle(
                        color: Color(0xFF4F46E5),
                        fontWeight: FontWeight.w700,
                        fontSize: 11,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          IconButton(
            tooltip: 'Hapus',
            onPressed: () => onDelete(item),
            icon: const Icon(Icons.delete_outline_rounded, color: Color(0xFFDC2626)),
          ),
        ],
      ),
    );
  }
}
