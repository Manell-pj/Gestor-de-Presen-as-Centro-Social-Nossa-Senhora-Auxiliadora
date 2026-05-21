<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nome *</label>
        <input type="text" name="nome" class="form-control" required>
        <div class="invalid-feedback">Indique o nome do turno.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Código</label>
        <input type="text" name="codigo" class="form-control">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Hora de entrada *</label>
        <input type="time" name="hora_entrada" class="form-control" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Hora de saída *</label>
        <input type="time" name="hora_saida" class="form-control" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Início pausa</label>
        <input type="time" name="inicio_pausa" class="form-control">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Fim pausa</label>
        <input type="time" name="fim_pausa" class="form-control">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Tolerância de atraso (min)</label>
        <input type="number" name="tolerancia_entrada_min" class="form-control" min="0" value="0">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Tolerância de saída (min)</label>
        <input type="number" name="tolerancia_saida_min" class="form-control" min="0" value="0">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Horas previstas</label>
        <input type="number" step="0.25" name="horas_previstas" class="form-control" min="0" value="8.00">
    </div>
    <div class="col-md-6 mb-3">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="turno_noturno" id="criarTurnoNoturno">
            <label class="form-check-label" for="criarTurnoNoturno">Turno noturno</label>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="ativo" id="criarTurnoAtivo" checked>
            <label class="form-check-label" for="criarTurnoAtivo">Ativo</label>
        </div>
    </div>
</div>
