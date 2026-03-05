enum TodoPriority {
  normal,
  high,
}

class TodoItem {
  final int id;
  final String title;
  final String description;
  final DateTime createdAt;
  final DateTime? dueDate;
  final TodoPriority priority;
  final bool completed;

  const TodoItem({
    required this.id,
    required this.title,
    required this.description,
    required this.createdAt,
    required this.dueDate,
    required this.priority,
    required this.completed,
  });

  TodoItem copyWith({
    int? id,
    String? title,
    String? description,
    DateTime? createdAt,
    DateTime? dueDate,
    TodoPriority? priority,
    bool? completed,
  }) {
    return TodoItem(
      id: id ?? this.id,
      title: title ?? this.title,
      description: description ?? this.description,
      createdAt: createdAt ?? this.createdAt,
      dueDate: dueDate ?? this.dueDate,
      priority: priority ?? this.priority,
      completed: completed ?? this.completed,
    );
  }
}

class TodoInput {
  final String title;
  final String description;
  final DateTime? dueDate;
  final TodoPriority priority;

  const TodoInput({
    required this.title,
    required this.description,
    required this.dueDate,
    required this.priority,
  });
}
