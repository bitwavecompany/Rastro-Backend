<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionCategory: string
{
    case Food = 'comida';
    case Transport = 'transporte';
    case Entertainment = 'entretenimiento';
    case Study = 'estudio';
    case Work = 'trabajo';
    case Other = 'otros';

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Comida',
            self::Transport => 'Transporte',
            self::Entertainment => 'Entretenimiento',
            self::Study => 'Estudio',
            self::Work => 'Trabajo',
            self::Other => 'Otros',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Food => 'utensils',
            self::Transport => 'car',
            self::Entertainment => 'film',
            self::Study => 'book-open',
            self::Work => 'briefcase',
            self::Other => 'more-horizontal',
        };
    }

    /**
     * @return array<int, array{id: string, name: string, icon: string}>
     */
    public static function toCatalog(): array
    {
        return array_map(
            fn (self $category) => [
                'id' => $category->value,
                'name' => $category->label(),
                'icon' => $category->icon(),
            ],
            self::cases()
        );
    }
}
