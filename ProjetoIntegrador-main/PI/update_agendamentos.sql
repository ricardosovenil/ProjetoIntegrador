USE sistema_tutoria;

ALTER TABLE agendamentos
ADD COLUMN local VARCHAR(255) AFTER horario_termino,
ADD COLUMN link_videoconferencia VARCHAR(255) AFTER local;

ALTER TABLE solicitacoes_tutoria
ADD COLUMN justificativa TEXT AFTER status; 