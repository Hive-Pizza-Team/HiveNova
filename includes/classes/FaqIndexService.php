<?php

namespace HiveNova\Core;

/**
 * Flattens $LNG['questions'] into a template-ready FAQ index.
 */
class FaqIndexService
{
	/**
	 * @param mixed $questions Nested FAQ map: category id => ['category' => string, int id => ['title' => string, 'body' => string]]
	 * @return list<array{categoryId: int, category: string, questions: list<array{categoryId: int, questionId: int, title: string}>}>
	 */
	public static function fromQuestions(mixed $questions): array
	{
		if (!is_array($questions)) {
			return [];
		}

		$index = [];

		foreach ($questions as $categoryKey => $categoryRow) {
			if (!is_numeric($categoryKey) || !is_array($categoryRow)) {
				continue;
			}

			$categoryId = (int) $categoryKey;
			$entries = [];

			foreach ($categoryRow as $questionKey => $questionRow) {
				if (!is_numeric($questionKey) || !is_array($questionRow)) {
					continue;
				}

				$title = $questionRow['title'] ?? null;
				if (!is_string($title) || $title === '') {
					continue;
				}

				$entries[] = [
					'categoryId' => $categoryId,
					'questionId' => (int) $questionKey,
					'title'      => $title,
				];
			}

			$categoryTitle = $categoryRow['category'] ?? '';
			$index[] = [
				'categoryId' => $categoryId,
				'category'   => is_string($categoryTitle) ? $categoryTitle : '',
				'questions'  => $entries,
			];
		}

		return $index;
	}
}
