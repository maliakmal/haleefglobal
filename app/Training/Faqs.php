<?php
declare(strict_types=1);

namespace Haleef\Training;

use PDO;
use RuntimeException;

final class Faqs
{
    public function __construct(private readonly PDO $pdo) {}

    public function library(): array
    {
        return $this->pdo->query('SELECT * FROM learning_faqs ORDER BY sort_order,id')->fetchAll();
    }

    public function sharedForEdition(int $edition): array
    {
        $query = $this->pdo->prepare('SELECT f.*, x.enabled, x.question AS local_question, x.answer AS local_answer,
            x.sort_order AS local_order FROM learning_faqs f LEFT JOIN learning_edition_faqs x
            ON x.faq_id=f.id AND x.edition_id=? ORDER BY f.sort_order,f.id');
        $query->execute([$edition]);
        return $query->fetchAll();
    }

    public function custom(int $edition): array
    {
        $query = $this->pdo->prepare('SELECT * FROM learning_edition_faqs WHERE edition_id=? AND faq_id IS NULL ORDER BY sort_order,id');
        $query->execute([$edition]);
        return $query->fetchAll();
    }

    public function visible(int $edition): array
    {
        $items = [];
        foreach ($this->sharedForEdition($edition) as $row) {
            if (!($row['enabled'] ?? $row['default_enabled'])) continue;
            $items[] = ['question'=>$row['local_question'] ?? $row['question'], 'answer'=>$row['local_answer'] ?? $row['answer'],
                'sort_order'=>$row['local_order'] ?? $row['sort_order']];
        }
        foreach ($this->custom($edition) as $row) if ($row['enabled']) $items[] = $row;
        usort($items, static fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
        return $items;
    }

    public static function validate(string $question, string $answer, string $order, bool $required): array
    {
        if (($required && ($question === '' || $answer === '')) || strlen($question) > 255 || strlen($answer) > 20000
            || ($order !== '' && !preg_match('/^\d{1,5}$/D', $order))) {
            throw new RuntimeException('Check the question, answer and display order. Questions and answers are required for standalone FAQs.');
        }
        return [$question !== '' ? $question : null, $answer !== '' ? $answer : null, $order !== '' ? (int)$order : null];
    }
}
