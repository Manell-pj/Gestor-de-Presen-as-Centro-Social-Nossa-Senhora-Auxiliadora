<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nome *</label>
        <input type="text" name="nome" class="form-control" value="<?php echo e($funcionarioForm['nome'] ?? ''); ?>" required>
        <div class="invalid-feedback">Indique o nome do funcionário.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Número mecanográfico</label>
        <input type="text" name="numero_mecanografico" class="form-control" value="<?php echo e($funcionarioForm['numero_mecanografico'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Função</label>
        <input type="text" name="funcao" class="form-control" value="<?php echo e($funcionarioForm['funcao'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?php echo e($funcionarioForm['email'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Telefone</label>
        <input type="text" name="telefone" class="form-control" value="<?php echo e($funcionarioForm['telefone'] ?? ''); ?>">
    </div>
    <?php if ($temEquipas): ?>
        <div class="col-md-6 mb-3">
            <label class="form-label">Equipa</label>
            <select name="equipa_id" class="form-select">
                <option value="">Sem equipa</option>
                <?php foreach ($equipas as $equipa): ?>
                    <option value="<?php echo (int) $equipa['id']; ?>" <?php echo (int) ($funcionarioForm['equipa_id'] ?? 0) === (int) $equipa['id'] ? 'selected' : ''; ?>>
                        <?php echo e($equipa['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="col-md-4 mb-3">
        <label class="form-label">Data admissao</label>
        <input type="date" name="data_admissao" class="form-control" value="<?php echo e($funcionarioForm['data_admissao'] ?? ''); ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Data cessacao</label>
        <input type="date" name="data_cessacao" class="form-control" value="<?php echo e($funcionarioForm['data_cessacao'] ?? ''); ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Carga semanal</label>
        <input type="number" step="0.25" min="0.25" name="carga_horaria_semanal" class="form-control" value="<?php echo e($funcionarioForm['carga_horaria_semanal'] ?? '40.00'); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tipo contrato</label>
        <input type="text" name="tipo_contrato" class="form-control" value="<?php echo e($funcionarioForm['tipo_contrato'] ?? ''); ?>">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select">
            <option value="ativo" <?php echo ($funcionarioForm['estado'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
            <option value="suspenso" <?php echo ($funcionarioForm['estado'] ?? '') === 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
            <option value="inativo" <?php echo ($funcionarioForm['estado'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">PIN ponto</label>
        <input type="text" name="pin_ponto" class="form-control" value="<?php echo e($funcionarioForm['pin_ponto'] ?? ''); ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Código cartão</label>
        <input type="text" name="codigo_cartao" class="form-control" value="<?php echo e($funcionarioForm['codigo_cartao'] ?? ''); ?>">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Código biométrico</label>
        <input type="text" name="codigo_biometrico" class="form-control" value="<?php echo e($funcionarioForm['codigo_biometrico'] ?? ''); ?>">
    </div>
    <div class="col-md-12 mb-3">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" class="form-control" rows="3"><?php echo e($funcionarioForm['observacoes'] ?? ''); ?></textarea>
    </div>
</div>

