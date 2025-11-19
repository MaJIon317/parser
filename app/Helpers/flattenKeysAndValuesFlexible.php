<?php
/*
 * Многомерный массив преобразовываем в плоский с возвожностью исключить ключи и значения
 *
 */
if (!function_exists('flattenKeysAndValuesFlexible')) {
    function flattenKeysAndValuesFlexible(
        array $array,
        array $skipKeysCompletely = [], // пропускать только ключи
        array $skipValuesForKeys = [], // пропускать только значения
        int $maxDepth = 200, // максимальная глубина рекурсии
        int $currentDepth = 0, // текущая глубина
        array &$visited = [] // проверка циклов
    ): array {
        $result = [];

        // Защита от слишком глубокой рекурсии
        if ($currentDepth > $maxDepth) {
            return $result;
        }

        // Защита от циклических ссылок
        if (in_array($array, $visited, true)) {
            return $result;
        }
        $visited[] = $array;

        foreach ($array as $key => $value) {

            // Если ключ не в списке пропускаемого по ключу
            if (!in_array($key, $skipKeysCompletely)) {
                $result[] = $key;
            }

            if (is_array($value)) {
                // рекурсивно обрабатываем вложенные массивы
                $result = array_merge(
                    $result,
                    flattenKeysAndValuesFlexible(
                        $value,
                        $skipKeysCompletely,
                        $skipValuesForKeys,
                        $maxDepth,
                        $currentDepth + 1,
                        $visited
                    )
                );
            } else {
                // добавляем значение только если ключ не в списке пропускаемого по значению
                if (!in_array($key, $skipValuesForKeys)) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }
}
