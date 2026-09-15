<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('solplanetbe');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

$icons = array(
	'inverter' => 'fas fa-solar-panel',
	'meter'    => 'fas fa-tachometer-alt',
	'battery'  => 'fas fa-car-battery',
);
?>

<div class="row row-overflow">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			<div class="cursor logoPrimary" id="bt_solplanetbeDiscover">
				<i class="fas fa-search"></i>
				<br>
				<span>{{Rechercher les appareils}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br>
				<span>{{Ajouter à la main}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
		</div>

		<legend><i class="fas fa-list"></i> {{Mes appareils}}</legend>
		<?php
		if (count($eqLogics) == 0) {
			echo '<div class="alert alert-info" style="margin:5px;">';
			echo '<b>{{Aucun appareil pour le moment. Pour démarrer :}}</b>';
			echo '<ol style="margin:5px 0 0 0;padding-left:20px;">';
			echo '<li>{{Relevez l\'adresse IP de votre clé de communication AiSWEI — celle que votre box attribue au module Wi-Fi ou Ethernet branché sur l\'onduleur, pas celle de la box elle-même. Fixez-la dans votre routeur : si elle change, le plugin ne trouvera plus rien.}}</li>';
			echo '<li>{{Cliquez sur « Rechercher les appareils » et saisissez cette adresse. Le plugin interroge la clé et vous montre ce qu\'elle déclare : le ou les onduleurs, le compteur s\'il y en a un, la batterie le cas échéant.}}</li>';
			echo '<li>{{Créez les appareils trouvés. Les commandes sont posées aussitôt, et le premier relevé part dans la foulée.}}</li>';
			echo '</ol>';
			echo '<span class="help-block" style="margin:8px 0 0 0;">{{Tout se passe sur votre réseau local : aucune donnée ne transite par le cloud du constructeur, et aucun compte n\'est nécessaire. Ce plugin n\'est pas affilié à Solplanet ni à AiSWEI.}}</span>';
			echo '</div>';
		}
		echo '<div class="input-group" style="margin:5px;">';
		echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
		echo '<div class="input-group-btn">';
		echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
		echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="eqLogicThumbnailContainer">';
		foreach ($eqLogics as $eqLogic) {
			$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
			$devtype = $eqLogic->getConfiguration('devtype', 'inverter');
			$icon = isset($icons[$devtype]) ? $icons[$devtype] : 'fas fa-solar-panel';
			echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
			echo '<i class="' . $icon . '" style="font-size:4em;"></i>';
			echo '<br>';
			echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
			echo '<span class="hiddenAsCard displayTableRight hidden">';
			echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
			echo '</span>';
			echo '</div>';
		}
		echo '</div>';
		?>
	</div>

	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex">
			<span class="input-group-btn">
				<a class="btn btn-default btn-sm eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span></a>
				<a class="btn btn-default btn-sm eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span></a>
				<a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
				<a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
			</span>
		</div>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i><span class="hidden-xs"> {{Équipement}}</span></a></li>
			<li role="presentation"><a href="#diagtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-stethoscope"></i><span class="hidden-xs"> {{Diagnostic}}</span></a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i><span class="hidden-xs"> {{Commandes}}</span></a></li>
		</ul>

		<div class="tab-content">
			<!-- ========================= ÉQUIPEMENT ========================= -->
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<br>
				<div class="col-lg-6">
					<form class="form-horizontal">
						<fieldset>
							<legend><i class="fas fa-tag"></i> {{Général}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Nom}}</label>
								<div class="col-sm-6">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Onduleur}}">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Objet parent}}</label>
								<div class="col-sm-6">
									<select class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
										foreach ((jeeObject::buildTree(null, false)) as $object) {
											echo '<option value="' . $object->getId() . '">' . $object->getHumanName(true, true) . '</option>';
										}
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Catégorie}}</label>
								<div class="col-sm-8">
									<?php
									foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
										echo '<label class="checkbox-inline">';
										echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '">' . $value['name'];
										echo '</label>';
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Activer}}</label>
								<div class="col-sm-8">
									<input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Visible}}</label>
								<div class="col-sm-8">
									<input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>
								</div>
							</div>
						</fieldset>
					</form>
				</div>

				<div class="col-lg-6">
					<form class="form-horizontal">
						<fieldset>
							<legend><i class="fas fa-plug"></i> {{Appareil}}</legend>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Type}}</label>
								<div class="col-sm-5">
									<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="devtype">
										<option value="inverter">{{Onduleur}}</option>
										<option value="meter">{{Compteur}}</option>
										<option value="battery">{{Batterie}}</option>
									</select>
								</div>
								<div class="col-sm-4">
									<span class="help-block" style="margin:0;">{{Ce que cet équipement représente derrière la clé.}}</span>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Adresse IP de la clé}}</label>
								<div class="col-sm-5">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="ip" placeholder="192.168.0.42">
								</div>
								<div class="col-sm-4">
									<span class="help-block" style="margin:0;">{{Celle du module de communication, port 8484. Réservez-la dans votre routeur.}}</span>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Numéro de série}}</label>
								<div class="col-sm-5">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="sn" placeholder="MA30006012290197">
								</div>
								<div class="col-sm-4">
									<span class="help-block" style="margin:0;">{{Celui de l'onduleur — la batterie s'interroge avec le même. Inutile pour un compteur.}}</span>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Puissance nominale}}</label>
								<div class="col-sm-5">
									<div class="input-group">
										<input type="number" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="rate" placeholder="3000">
										<span class="input-group-addon">W</span>
									</div>
								</div>
								<div class="col-sm-4">
									<span class="help-block" style="margin:0;">{{Lue toute seule sur la clé. Elle sert d'échelle à la jauge de la tuile : sans elle, 2 500 W ne dit pas si l'onduleur est à la peine ou au maximum.}}</span>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Relever toutes les}}</label>
								<div class="col-sm-5">
									<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="interval">
										<option value="1">{{minute}}</option>
										<option value="2">{{2 minutes}}</option>
										<option value="5">{{5 minutes}}</option>
										<option value="10">{{10 minutes}}</option>
										<option value="15">{{15 minutes}}</option>
										<option value="30">{{30 minutes}}</option>
									</select>
								</div>
								<div class="col-sm-4">
									<span class="help-block" style="margin:0;">{{Cinq minutes suffisent pour suivre une production. La minute est possible — l'appel se fait sur votre réseau local — mais elle remplit l'historique douze fois plus vite.}}</span>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label">{{Hors ligne}}</label>
								<div class="col-sm-9">
									<label class="checkbox-inline">
										<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="zero_when_offline" value="1">{{mettre les puissances à zéro}}
									</label>
									<span class="help-block" style="margin:4px 0 0 0;">{{La clé est alimentée par l'onduleur : sur une installation sans batterie, elle s'éteint avec le soleil. Cochée, la production retombe à zéro le soir au lieu de rester figée sur la dernière valeur. Décochez-la pour un onduleur hybride, qui reste allumé la nuit : une absence de réponse y serait une vraie panne. Les compteurs d'énergie, eux, ne sont jamais remis à zéro.}}</span>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-3 control-label"></label>
								<div class="col-sm-9">
									<a class="btn btn-default btn-sm" id="bt_solplanetbeRefresh"><i class="fas fa-sync"></i> {{Relever maintenant}}</a>
									<span id="span_solplanetbeStatus" style="margin-left:10px;"></span>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
			</div>

			<!-- ========================== DIAGNOSTIC ========================= -->
			<div role="tabpanel" class="tab-pane" id="diagtab">
				<br>
				<div class="col-xs-12">
					<div class="alert alert-info" id="div_solplanetbeState">{{Chargement…}}</div>
					<legend><i class="fas fa-code"></i> {{Dernière réponse de la clé}}</legend>
					<span class="help-block">{{Le JSON tel que la clé l'a renvoyé, avant toute mise à l'échelle. C'est la pièce à joindre en cas de valeur douteuse : elle dit si le problème vient de l'onduleur ou du plugin.}}</span>
					<pre id="pre_solplanetbeRaw" style="max-height:420px;overflow:auto;"></pre>
				</div>
			</div>

			<!-- ========================== COMMANDES ========================== -->
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<br>
				<div class="col-xs-12">
					<a class="btn btn-default btn-sm pull-right" id="bt_solplanetbeHideHidden"><i class="fas fa-eye"></i> {{Afficher seulement les commandes visibles}}</a>
					<br><br>
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th style="width:250px;">{{Nom}}</th>
								<th style="width:120px;">{{Type}}</th>
								<th>{{Paramètres}}</th>
								<th style="width:120px;">{{Actions}}</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include_file('desktop', 'solplanetbe', 'js', 'solplanetbe'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
