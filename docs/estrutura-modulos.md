# Estrutura de Modulos - Assiduidade e RH

Este projeto deve manter uma base simples em PHP procedural com MySQLi, usando os includes ja existentes:

- `includes/head.php`
- `includes/header.php`
- `includes/sidebar.php`
- `includes/footer.php`
- `includes/scripts.php`
- `config.php`

A ideia e organizar a aplicacao por modulos funcionais, cada um com paginas proprias, ficheiros de acoes e consultas SQL simples.

## Estrutura Recomendada

```text
/
├── index.php
├── principal.php
├── config.php
├── includes/
│   ├── head.php
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   ├── scripts.php
│   ├── auth.php
│   ├── permissions.php
│   └── helpers.php
├── modules/
│   ├── dashboard/
│   │   ├── index.php
│   │   └── widgets.php
│   ├── colaboradores/
│   │   ├── index.php
│   │   ├── criar.php
│   │   ├── editar.php
│   │   ├── ver.php
│   │   └── acoes.php
│   ├── departamentos/
│   │   ├── index.php
│   │   ├── criar.php
│   │   ├── editar.php
│   │   └── acoes.php
│   ├── horarios/
│   │   ├── index.php
│   │   ├── turnos.php
│   │   ├── escalas.php
│   │   ├── atribuir.php
│   │   └── acoes.php
│   ├── ponto/
│   │   ├── index.php
│   │   ├── registos.php
│   │   ├── manual.php
│   │   ├── importar.php
│   │   └── acoes.php
│   ├── ausencias/
│   │   ├── index.php
│   │   ├── ferias.php
│   │   ├── faltas.php
│   │   ├── pedidos.php
│   │   ├── aprovar.php
│   │   └── acoes.php
│   ├── banco_horas/
│   │   ├── index.php
│   │   ├── movimentos.php
│   │   ├── ajustes.php
│   │   └── acoes.php
│   ├── relatorios/
│   │   ├── index.php
│   │   ├── assiduidade.php
│   │   ├── horas.php
│   │   ├── ausencias.php
│   │   └── exportar.php
│   ├── dispositivos/
│   │   ├── index.php
│   │   ├── criar.php
│   │   ├── sincronizar.php
│   │   ├── logs.php
│   │   └── acoes.php
│   └── permissoes/
│       ├── utilizadores.php
│       ├── papeis.php
│       ├── permissoes.php
│       └── acoes.php
├── uploads/
│   ├── colaboradores/
│   └── importacoes/
└── docs/
    └── estrutura-modulos.md
```

## Convencao de Cada Modulo

Cada modulo deve seguir uma organizacao previsivel:

- `index.php`: listagem principal do modulo.
- `criar.php`: formulario de criacao, quando aplicavel.
- `editar.php`: formulario de edicao, quando aplicavel.
- `ver.php`: detalhe de um registo, quando aplicavel.
- `acoes.php`: tratamento de `POST`, criacao, atualizacao, remocao, ativacao e outras operacoes.
- ficheiros especificos: paginas proprias do dominio, como `turnos.php`, `ferias.php`, `sincronizar.php` ou `exportar.php`.

As paginas visuais devem incluir o layout base:

```php
include '../../config.php';
include '../../includes/head.php';
include '../../includes/sidebar.php';
include '../../includes/header.php';
include '../../includes/footer.php';
include '../../includes/scripts.php';
```

O caminho pode variar conforme a localizacao do ficheiro.

## Modulos

### Dashboard e Relatorios

Objetivo: dar uma visao rapida da assiduidade, atrasos, ausencias, horas extra e estado dos dispositivos.

Paginas principais:

- `modules/dashboard/index.php`
- `modules/dashboard/widgets.php`
- `modules/relatorios/index.php`
- `modules/relatorios/assiduidade.php`
- `modules/relatorios/horas.php`
- `modules/relatorios/ausencias.php`
- `modules/relatorios/exportar.php`

Indicadores uteis:

- colaboradores presentes hoje
- colaboradores ausentes hoje
- atrasos do dia
- picagens incompletas
- saldo total de banco de horas
- pedidos de ferias pendentes
- dispositivos offline

### Colaboradores e Utilizadores

Objetivo: gerir dados pessoais, profissionais e acesso ao sistema.

Paginas principais:

- `modules/colaboradores/index.php`
- `modules/colaboradores/criar.php`
- `modules/colaboradores/editar.php`
- `modules/colaboradores/ver.php`
- `modules/colaboradores/acoes.php`

Dados principais:

- nome
- email
- telefone
- numero mecanografico
- departamento
- cargo
- tipo de contrato
- data de entrada
- estado: ativo, suspenso, inativo
- fotografia
- utilizador associado
- identificador biometrico ou cartao RFID

Tabelas sugeridas:

- `colaboradores`
- `utilizadores`
- `colaborador_documentos`

### Departamentos

Objetivo: organizar colaboradores por areas, equipas ou centros de custo.

Paginas principais:

- `modules/departamentos/index.php`
- `modules/departamentos/criar.php`
- `modules/departamentos/editar.php`
- `modules/departamentos/acoes.php`

Dados principais:

- nome
- codigo
- responsavel
- estado

Tabelas sugeridas:

- `departamentos`

### Horarios, Turnos e Escalas

Objetivo: definir regras de trabalho e associar horarios aos colaboradores.

Paginas principais:

- `modules/horarios/index.php`
- `modules/horarios/turnos.php`
- `modules/horarios/escalas.php`
- `modules/horarios/atribuir.php`
- `modules/horarios/acoes.php`

Funcionalidades:

- horario fixo
- horario flexivel
- turnos rotativos
- tolerancia de entrada e saida
- pausa de almoco
- horas previstas por dia
- atribuicao por colaborador, departamento ou periodo

Tabelas sugeridas:

- `horarios`
- `turnos`
- `turno_dias`
- `colaborador_horarios`
- `escalas`

### Registos de Ponto

Objetivo: guardar entradas, saidas, pausas e correcao manual de picagens.

Paginas principais:

- `modules/ponto/index.php`
- `modules/ponto/registos.php`
- `modules/ponto/manual.php`
- `modules/ponto/importar.php`
- `modules/ponto/acoes.php`

Origem dos registos:

- manual
- dispositivo biometrico
- importacao CSV/Excel
- API futura

Estados importantes:

- valido
- pendente
- corrigido
- rejeitado
- duplicado

Tabelas sugeridas:

- `registos_ponto`
- `registos_ponto_logs`
- `correcoes_ponto`

### Ferias, Faltas e Ausencias

Objetivo: gerir pedidos, aprovacoes e justificacoes.

Paginas principais:

- `modules/ausencias/index.php`
- `modules/ausencias/ferias.php`
- `modules/ausencias/faltas.php`
- `modules/ausencias/pedidos.php`
- `modules/ausencias/aprovar.php`
- `modules/ausencias/acoes.php`

Tipos de ausencia:

- ferias
- falta justificada
- falta injustificada
- baixa medica
- licenca
- formacao
- teletrabalho

Estados:

- pendente
- aprovado
- rejeitado
- cancelado

Tabelas sugeridas:

- `tipos_ausencia`
- `ausencias`
- `ausencia_aprovacoes`

### Banco de Horas

Objetivo: controlar credito e debito de horas por colaborador.

Paginas principais:

- `modules/banco_horas/index.php`
- `modules/banco_horas/movimentos.php`
- `modules/banco_horas/ajustes.php`
- `modules/banco_horas/acoes.php`

Tipos de movimento:

- credito por hora extra
- debito por saida antecipada
- ajuste manual
- compensacao aprovada
- regularizacao mensal

Tabelas sugeridas:

- `banco_horas`
- `banco_horas_movimentos`

### Dispositivos Biometricos

Objetivo: preparar a integracao com relogios de ponto, terminais biometricos, RFID ou reconhecimento facial.

Paginas principais:

- `modules/dispositivos/index.php`
- `modules/dispositivos/criar.php`
- `modules/dispositivos/sincronizar.php`
- `modules/dispositivos/logs.php`
- `modules/dispositivos/acoes.php`

Dados principais:

- nome
- marca
- modelo
- ip
- porta
- localizacao
- estado
- ultima sincronizacao

Tabelas sugeridas:

- `dispositivos`
- `dispositivo_logs`
- `dispositivo_colaboradores`

Notas:

- Numa primeira fase, guardar apenas a configuracao e simular sincronizacoes.
- Depois, criar importacao por CSV.
- So numa fase posterior integrar SDK/API especifica do equipamento.

### Permissoes e Papeis

Objetivo: controlar o que cada utilizador pode ver e fazer.

Paginas principais:

- `modules/permissoes/utilizadores.php`
- `modules/permissoes/papeis.php`
- `modules/permissoes/permissoes.php`
- `modules/permissoes/acoes.php`

Papeis iniciais:

- administrador
- recursos humanos
- chefia
- colaborador

Permissoes sugeridas:

- `dashboard.ver`
- `colaboradores.ver`
- `colaboradores.criar`
- `colaboradores.editar`
- `departamentos.gerir`
- `horarios.gerir`
- `ponto.ver`
- `ponto.corrigir`
- `ausencias.pedir`
- `ausencias.aprovar`
- `banco_horas.ver`
- `banco_horas.ajustar`
- `relatorios.ver`
- `relatorios.exportar`
- `dispositivos.gerir`
- `permissoes.gerir`

Tabelas sugeridas:

- `papeis`
- `permissoes`
- `papel_permissoes`
- `utilizador_papeis`

## Ordem Recomendada de Implementacao

1. Base do projeto: `config.php`, sessao, login e includes comuns.
2. Permissoes e papeis simples.
3. Departamentos.
4. Colaboradores/utilizadores.
5. Horarios e turnos.
6. Registos de ponto manuais.
7. Ferias, faltas e ausencias.
8. Banco de horas.
9. Dashboard e relatorios.
10. Dispositivos biometricos e importacoes.

## Menu Lateral Sugerido

```text
Dashboard
Colaboradores
Departamentos
Horarios e Turnos
Registos de Ponto
Ferias e Ausencias
Banco de Horas
Relatorios
Dispositivos
Permissoes
Configuracoes
```

## Regras Gerais Para PHP Procedural

- Centralizar a ligacao MySQLi em `config.php`.
- Usar `mysqli_prepare` nas queries com dados do utilizador.
- Manter `acoes.php` apenas para processar formularios e redirecionar.
- Evitar HTML dentro de funcoes grandes.
- Criar funcoes pequenas em `includes/helpers.php`.
- Validar permissoes antes de mostrar paginas sensiveis.
- Guardar logs para operacoes importantes: correcao de ponto, aprovacoes, ajustes e sincronizacoes.
- Nunca apagar registos criticos de assiduidade; usar estado `inativo`, `cancelado` ou `anulado`.

## Nomeclatura Recomendada

- Pastas em minusculas e sem acentos.
- Tabelas no plural: `colaboradores`, `departamentos`, `registos_ponto`.
- Chaves primarias como `id`.
- Chaves estrangeiras como `colaborador_id`, `departamento_id`, `utilizador_id`.
- Datas como `data_criacao`, `data_atualizacao`, `criado_por`, `atualizado_por`.

