<?php

namespace App\Support;

/**
 * Подготовка пользовательского ввода для LIKE-поиска.
 *
 * В шаблоне LIKE символы `%` и `_` — подстановочные: без экранирования запрос
 * вроде «_» совпадает с любой строкой, а «100%» — со всем, где есть «100».
 * Значение экранируется, а SQL обязан указать тот же символ ESCAPE (см.
 * expression()), иначе обратный слэш станет литералом.
 */
final class LikePattern
{
    /** Символ ESCAPE, которым помечены подстановочные знаки в шаблоне. */
    public const ESCAPE = '\\';

    /**
     * Шаблон «содержит подстроку» с экранированными подстановочными знаками.
     */
    public static function contains(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }

    /**
     * Фрагмент SQL для колонки: `<колонка> LIKE ? ESCAPE '\'`.
     * Колонка подставляется вызывающим кодом и всегда является литералом.
     */
    public static function expression(string $column): string
    {
        return $column." LIKE ? ESCAPE '".self::ESCAPE."'";
    }
}
