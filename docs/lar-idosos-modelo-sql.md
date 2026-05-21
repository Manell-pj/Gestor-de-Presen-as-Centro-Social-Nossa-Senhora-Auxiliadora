# Adaptacao SQL para lar de idosos

Ficheiro principal: `database/2026_05_15_lar_idosos_assiduidade.sql`.

## Diagnostico da estrutura atual

O projeto ja tem uma base funcional para assiduidade, mas a separacao correta passa a ser:

- `funcionarios` guarda as pessoas que trabalham na instituicao e que vao picar ponto.
- `utilizadores` fica reservado apenas para quem acede ao site, como secretaria, direcao ou RH.
- `equipas` organiza os funcionarios por funcao operacional.
- `turnos` define horarios simples com uma entrada e uma saida.
- `horarios_turno` associa turnos a funcionarios por periodo e dia da semana.
- `registos_ponto` guarda as picagens, sempre ligadas a `funcionario_id`.
- `tipos_ausencia` e `pedidos_ausencia` tratam ferias/faltas.
- `banco_horas` guarda creditos/debitos.

Para um lar de idosos faltavam duas camadas:

- equipas operacionais, porque a secretaria precisa de filtrar por funcao real;
- turnos com varios periodos no mesmo dia, porque ha horarios repartidos;
- tabelas de apuramento, para fechar dias e meses sem recalcular tudo em cada relatorio.

## Alteracoes propostas

A migration cria sem apagar dados:

- `equipas`
- `funcionarios`
- `turno_periodos`
- `escala_mensal`
- `escala_mensal_dias`
- `ferias_ausencias`
- `resumo_diario_assiduidade`
- `relatorios_mensais`
- `relatorio_mensal_linhas`

Tambem acrescenta colunas a tabelas existentes:

- `utilizadores`: `equipa_id`, `funcionario_id`
- `turnos`: `descricao`, `total_periodos`, `permite_multiplos_periodos`, `tolerancia_antes_min`, `tolerancia_depois_min`
- `horarios_turno`: `funcionario_id`
- `registos_ponto`: ligacao a funcionario, escala, periodo do turno e campos de tolerancia/desvio
- `banco_horas`: ligacao a funcionario e resumo diario

## Dados iniciais

Sao inseridas equipas operacionais iniciais:

- Acao Direta
- Servicos Gerais
- Refeitorio/Copa
- Cozinha
- Lavandaria
- Servicos Tecnicos e Administrativos
- Motoristas

E os turnos indicados pela instituicao, com tolerancia de 15 minutos antes e 15 minutos depois:

- 00:00-08:00
- 08:00-16:00
- 16:00-00:00
- 08:30-16:30
- 08:00-13:00 e 17:00-20:00
- 08:00-14:00
- 08:00-12:00 e 18:00-20:00
- 12:00-20:00
- 09:00-12:30 e 14:00-17:30

## Compatibilidade PHP/MySQLi

O codigo operacional deve consultar `funcionarios`, `equipas`, `turnos`, `pedidos_ausencia`, `registos_ponto` e `banco_horas`.
`utilizadores` deve ser usado apenas para login e auditoria de quem fez alteracoes no site.

Para novos ecras, usar sempre `mysqli_prepare`, por exemplo:

```php
$stmt = mysqli_prepare($conn, 'SELECT funcionario_id, nome, equipa_nome FROM vw_funcionarios_contexto WHERE estado = ? ORDER BY nome');
$estado = 'ativo';
mysqli_stmt_bind_param($stmt, 's', $estado);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
```

Views criadas para simplificar listagens:

- `vw_funcionarios_contexto`
- `vw_turnos_periodos`
- `vw_relatorio_mensal_assiduidade`

## Fluxo recomendado

1. A secretaria cria/atualiza funcionarios e equipas.
2. O leitor biometrico identifica o funcionario por `codigo_biometrico` e cria `registos_ponto` com origem `dispositivo`.
3. Os funcionarios picam apenas `entrada` e `saida`.
4. A secretaria regista ou corrige pausas, faltas e ajustes manuais quando necessario.
5. A escala mensal e criada em `escala_mensal` e preenchida em `escala_mensal_dias`.
6. Um processo PHP de apuramento diario grava `resumo_diario_assiduidade`.
7. Os relatorios mensais fechados sao guardados em `relatorios_mensais` e `relatorio_mensal_linhas`.

Esta abordagem evita apagar registos criticos: ausencias ficam canceladas/rejeitadas, registos de ponto ficam anulados/corrigidos, e relatorios fechados podem ser preservados como historico.
