-- Create the database
CREATE DATABASE IF NOT EXISTS sistema_tutoria;
USE sistema_tutoria;

-- Create areas table
CREATE TABLE IF NOT EXISTS areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create tutores table
CREATE TABLE IF NOT EXISTS tutores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telefone VARCHAR(20) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    dia_semana ENUM('Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta', 'Sabado', 'Domingo') NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_termino TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create areas_tutor table (many-to-many relationship between tutores and areas)
CREATE TABLE IF NOT EXISTS areas_tutor (
    tutor_id INT,
    area_id INT,
    PRIMARY KEY (tutor_id, area_id),
    FOREIGN KEY (tutor_id) REFERENCES tutores(id) ON DELETE CASCADE,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE
);

-- Create estudantes table
CREATE TABLE IF NOT EXISTS estudantes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    curso VARCHAR(100) NOT NULL,
    matricula VARCHAR(20) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create agendamentos table
CREATE TABLE IF NOT EXISTS agendamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tutor_id INT NOT NULL,
    estudante_id INT NOT NULL,
    data DATE NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_termino TIME NOT NULL,
    status ENUM('pendente', 'confirmado', 'cancelado', 'concluido') DEFAULT 'pendente',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutores(id) ON DELETE CASCADE,
    FOREIGN KEY (estudante_id) REFERENCES estudantes(id) ON DELETE CASCADE
);

-- Create avaliacoes table
CREATE TABLE IF NOT EXISTS avaliacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agendamento_id INT NOT NULL,
    nota INT NOT NULL CHECK (nota >= 1 AND nota <= 5),
    comentario TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE
);

-- Insert default areas
INSERT INTO areas (nome) VALUES
('Prática de Programação 1'),
('Qualidade de Software'),
('Sistemas Operacionais'),
('Estrutura de Dados'),
('Banco de Dados 1'); 