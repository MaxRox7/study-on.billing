<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken as BaseRefreshToken;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    #[ORM\Column(name: 'refresh_token', type: 'string', length: 128, unique: true)]
    protected $refreshToken;

    #[ORM\Column(name: 'username', type: 'string', length: 255)]
    protected $username;

    #[ORM\Column(type: 'datetime')]
    protected $valid;
}
