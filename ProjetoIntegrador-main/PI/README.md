# Sistema de Mentoria Acadêmica

Sistema desenvolvido para gerenciar o processo de mentoria acadêmica, permitindo o agendamento de sessões entre estudantes e tutores.

## Funcionalidades

- Cadastro e autenticação de usuários (Estudantes, Tutores e Coordenadores)
- Agendamento de sessões de mentoria
- Gerenciamento de horários e disponibilidade
- Sistema de notificações
- Recuperação de senha
- Painel administrativo para coordenadores

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache/Nginx)
- Extensões PHP:
  - PDO
  - PDO_MySQL
  - mbstring
  - json

## Instalação

1. Clone o repositório:
```bash
git clone https://github.com/seu-usuario/sistema-mentoria.git
```

2. Configure o banco de dados:
   - Crie um banco de dados MySQL
   - Importe o arquivo `database.sql`
   - Copie o arquivo `config.example.php` para `config.php`
   - Configure as credenciais do banco de dados em `config.php`

3. Configure o servidor web:
   - Aponte o DocumentRoot para o diretório do projeto
   - Certifique-se que o mod_rewrite está habilitado (Apache)

4. Permissões:
   - Certifique-se que o diretório de uploads tem permissão de escrita
   - Configure as permissões corretas para os arquivos de log

## Estrutura do Projeto

```
├── assets/          # Arquivos estáticos (CSS, JS, imagens)
├── includes/        # Arquivos de inclusão PHP
├── uploads/         # Diretório para uploads
├── config.php       # Configurações do sistema
├── database.sql     # Script do banco de dados
└── README.md        # Este arquivo
```

## Uso

1. Acesse o sistema através do navegador
2. Faça login com as credenciais de teste:
   - Coordenador: coordenador@teste.com / password
   - Tutor: tutor@teste.com / password
   - Estudante: estudante@teste.com / password

## Segurança

- Todas as senhas são armazenadas com hash bcrypt
- Proteção contra SQL Injection usando PDO
- Validação de entrada de dados
- Proteção contra XSS
- Tokens CSRF em formulários

## Contribuição

1. Faça um Fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/nova-feature`)
3. Commit suas mudanças (`git commit -m 'Adiciona nova feature'`)
4. Push para a branch (`git push origin feature/nova-feature`)
5. Abra um Pull Request

## Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes. 