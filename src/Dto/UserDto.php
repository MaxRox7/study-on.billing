<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserDto
{
    #[Assert\NotBlank(message: "Email обязателен")]
    #[Assert\Email(message: "Неверный формат email")]
    public string $email;

    #[Assert\NotBlank(message: "Пароль обязателен")]
    #[Assert\Length(
        min: 6,
        minMessage: "Пароль должен содержать минимум {{ limit }} символов"
    )]
    public string $password;

    #[Assert\NotBlank(message: "Роли обязательны")]
    #[Assert\Type("array", message: "Роли должны быть массивом")]
    #[Assert\All([
        new Assert\Choice(
            choices: ["ROLE_USER", "ROLE_ADMIN"],
            message: "Каждая роль должна быть либо ROLE_USER, либо ROLE_ADMIN"
        )
    ])]
    public array $roles = ['ROLE_USER'];
}
