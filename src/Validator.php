<?php
declare(strict_types=1);

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string ...$fields): self {
        foreach ($fields as $field) {
            if (empty($this->data[$field]) && !is_numeric($this->data[$field])) {
                $this->errors[$field] = "Dit veld is verplicht.";
            }
        }
        return $this;
    }

    public function email(string $field): self {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Ongeldig e-mailadres.";
        }
        return $this;
    }

    public function min(string $field, int $min): self {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field] = "Moet minimaal $min tekens bevatten.";
        }
        return $this;
    }

    public function matches(string $field, string $matchField, string $matchLabel): self {
        if (($this->data[$field] ?? '') !== ($this->data[$matchField] ?? '')) {
            $this->errors[$field] = "Komt niet overeen met $matchLabel.";
        }
        return $this;
    }

    public function isValid(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }
    
    public function getFirstError(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        return reset($this->errors);
    }
}
