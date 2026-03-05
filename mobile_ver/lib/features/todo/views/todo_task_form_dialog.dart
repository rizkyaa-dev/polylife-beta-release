import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import 'package:mobile_ver/features/todo/models/todo_item.dart';

Future<TodoInput?> showTodoTaskFormDialog(BuildContext context) {
  return showDialog<TodoInput>(
    context: context,
    builder: (context) => const _TodoTaskFormDialog(),
  );
}

class _TodoTaskFormDialog extends StatefulWidget {
  const _TodoTaskFormDialog();

  @override
  State<_TodoTaskFormDialog> createState() => _TodoTaskFormDialogState();
}

class _TodoTaskFormDialogState extends State<_TodoTaskFormDialog> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  TodoPriority _priority = TodoPriority.normal;
  DateTime? _dueDate;

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _pickDueDate() async {
    final now = DateTime.now();
    final pickedDate = await showDatePicker(
      context: context,
      initialDate: _dueDate ?? now,
      firstDate: now.subtract(const Duration(days: 365)),
      lastDate: now.add(const Duration(days: 3650)),
    );
    if (pickedDate == null || !mounted) return;

    final pickedTime = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_dueDate ?? now),
    );
    if (pickedTime == null || !mounted) return;

    setState(() {
      _dueDate = DateTime(
        pickedDate.year,
        pickedDate.month,
        pickedDate.day,
        pickedTime.hour,
        pickedTime.minute,
      );
    });
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;

    Navigator.of(context).pop(
      TodoInput(
        title: _titleController.text.trim(),
        description: _descriptionController.text.trim(),
        dueDate: _dueDate,
        priority: _priority,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final dueLabel =
        _dueDate == null ? 'Atur deadline (opsional)' : DateFormat('dd MMM yyyy, HH:mm', 'id_ID').format(_dueDate!);

    return AlertDialog(
      title: const Text('Tugas Baru'),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: _titleController,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(labelText: 'Judul tugas'),
                validator: (value) {
                  if ((value ?? '').trim().isEmpty) {
                    return 'Judul tugas wajib diisi.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _descriptionController,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(labelText: 'Deskripsi (opsional)'),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<TodoPriority>(
                initialValue: _priority,
                decoration: const InputDecoration(labelText: 'Prioritas'),
                items: const [
                  DropdownMenuItem(
                    value: TodoPriority.normal,
                    child: Text('Normal'),
                  ),
                  DropdownMenuItem(
                    value: TodoPriority.high,
                    child: Text('Tinggi'),
                  ),
                ],
                onChanged: (value) {
                  if (value == null) return;
                  setState(() => _priority = value);
                },
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _pickDueDate,
                icon: const Icon(Icons.event_outlined),
                label: Text(dueLabel),
              ),
              if (_dueDate != null)
                TextButton(
                  onPressed: () => setState(() => _dueDate = null),
                  child: const Text('Hapus deadline'),
                ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: _submit,
          child: const Text('Simpan'),
        ),
      ],
    );
  }
}
