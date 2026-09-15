/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* ================================================================== OUTILS */

function solplanetbeEl(_id) {
  return document.getElementById(_id)
}

/* Tout ce qui vient de la clé est du texte, jamais du balisage : un numéro de
   série ou un nom de modèle n'a pas à pouvoir écrire dans la page. */
function solplanetbeEscape(_text) {
  var div = document.createElement('div')
  div.textContent = (_text === null || _text === undefined) ? '' : String(_text)
  return div.innerHTML
}

function solplanetbeAjax(_action, _data, _success) {
  var payload = { action: _action }
  for (var key in _data) {
    if (Object.prototype.hasOwnProperty.call(_data, key)) { payload[key] = _data[key] }
  }
  domUtils.ajax({
    type: 'POST',
    url: 'plugins/solplanetbe/core/ajax/solplanetbe.ajax.php',
    data: payload,
    dataType: 'json',
    /* Le callback d'erreur de domUtils.ajax ne reçoit qu'un seul argument,
       contrairement à celui de jQuery. */
    error: function (error) {
      domUtils.handleAjaxError(error)
    },
    success: _success
  })
}

function solplanetbeStatus(_text, _level) {
  var span = solplanetbeEl('span_solplanetbeStatus')
  if (span === null) { return }
  span.textContent = _text
  span.className = _level ? 'label label-' + _level : ''
}

var solplanetbeTypeNames = {
  inverter: '{{Onduleur}}',
  meter: '{{Compteur}}',
  battery: '{{Batterie}}'
}

/* ============================================================== DÉCOUVERTE */

/* L'adresse est demandée puis retenue dans le navigateur : on relance une
   découverte après avoir ajouté un onduleur ou changé de clé, et retaper
   l'adresse à chaque fois serait une corvée inutile. */
function solplanetbeDiscover() {
  var last = ''
  try { last = window.localStorage.getItem('solplanetbe:ip') || '' } catch (e) { last = '' }

  bootbox.prompt({
    title: '{{Adresse IP de la clé de communication AiSWEI}}',
    value: last,
    placeholder: '192.168.0.42',
    callback: function (_ip) {
      if (_ip === null) { return }
      var ip = String(_ip).trim()
      if (ip === '') {
        jeedomUtils.showAlert({ message: '{{Saisissez l\'adresse IP de la clé, pas celle de votre box.}}', level: 'warning' })
        return
      }
      try { window.localStorage.setItem('solplanetbe:ip', ip) } catch (e) { /* mode privé */ }

      jeedomUtils.showAlert({ message: '{{Interrogation de la clé…}}', level: 'info' })
      solplanetbeAjax('discover', { ip: ip }, function (result) {
        solplanetbeShowDiscovery(result.result)
      })
    }
  })
}

function solplanetbeShowDiscovery(_devices) {
  if (!isset(_devices) || _devices.length === 0) {
    jeedomUtils.showAlert({ message: '{{La clé a répondu, mais n\'a déclaré aucun appareil.}}', level: 'warning' })
    return
  }

  var lines = '<p>{{Voici ce que la clé déclare. Les appareils déjà créés ne le seront pas une seconde fois.}}</p><ul>'
  for (var i = 0; i < _devices.length; i++) {
    var device = _devices[i]
    var type = solplanetbeTypeNames[device.devtype] || device.devtype
    lines += '<li><b>' + solplanetbeEscape(type) + '</b>'
    if (device.model) { lines += ' — ' + solplanetbeEscape(device.model) }
    if (device.sn) { lines += ' <small>(' + solplanetbeEscape(device.sn) + ')</small>' }
    lines += '</li>'
  }
  lines += '</ul>'

  bootbox.confirm({
    title: '{{Appareils trouvés}}',
    message: lines,
    callback: function (_confirmed) {
      if (!_confirmed) { return }
      solplanetbeAjax('createDevices', { devices: JSON.stringify(_devices) }, function (result) {
        var created = parseInt(result.result, 10)
        if (created === 0) {
          jeedomUtils.showAlert({ message: '{{Tous ces appareils existaient déjà : rien n\'a été créé.}}', level: 'warning' })
          return
        }
        jeedomUtils.showAlert({ message: created + ' {{appareil(s) créé(s). Le premier relevé est lancé.}}', level: 'success' })
        /* La liste des vignettes est construite côté PHP : il faut recharger la
           page pour la voir, le coeur ne sait pas l'enrichir à chaud. */
        jeedomUtils.loadPage('index.php?v=d&p=solplanetbe')
      })
    }
  })
}

/* ============================================================== DIAGNOSTIC */

function solplanetbeClear() {
  var raw = solplanetbeEl('pre_solplanetbeRaw')
  if (raw !== null) { raw.textContent = '' }
  var state = solplanetbeEl('div_solplanetbeState')
  if (state !== null) {
    state.className = 'alert alert-info'
    state.textContent = '{{Chargement…}}'
  }
}

function solplanetbeRender(_data) {
  var state = solplanetbeEl('div_solplanetbeState')
  if (state !== null) {
    if (!isset(_data) || _data.fetchedAt === '') {
      state.className = 'alert alert-warning'
      state.textContent = '{{Aucun relevé pour le moment. Enregistrez l\'appareil, puis utilisez « Relever maintenant ».}}'
    } else if (_data.online) {
      state.className = 'alert alert-success'
      state.textContent = '{{Dernier relevé :}} ' + _data.fetchedAt
        + ' · ' + _data.phases + ' {{phase(s)}} · ' + _data.mppt + ' {{chaîne(s) photovoltaïque(s)}}'
    } else {
      state.className = 'alert alert-warning'
      state.textContent = '{{Hors ligne depuis}} ' + _data.failures + ' {{tentative(s)}}'
        + (_data.problem ? ' — ' + _data.problem : '')
        + ' · {{dernier relevé réussi :}} ' + (_data.fetchedAt || '{{jamais}}')
    }
  }

  var raw = solplanetbeEl('pre_solplanetbeRaw')
  if (raw !== null) {
    raw.textContent = (isset(_data) && _data.raw) ? JSON.stringify(_data.raw, null, 2) : ''
  }
}

function solplanetbeLoad(_id) {
  if (!isset(_id) || _id === '') { return }
  solplanetbeAjax('data', { id: _id }, function (result) {
    solplanetbeRender(result.result)
  })
}

function solplanetbeRefresh() {
  var id = document.querySelector('.eqLogicAttr[data-l1key="id"]')
  if (id === null || id.value === '') {
    jeedomUtils.showAlert({ message: '{{Enregistrez l\'appareil avant de le relever.}}', level: 'warning' })
    return
  }
  solplanetbeStatus('{{Lecture en cours…}}', 'default')
  solplanetbeAjax('refresh', { id: id.value }, function (result) {
    solplanetbeStatus('{{Relevé effectué.}}', 'success')
    solplanetbeRender(result.result)
  })
}

/* ==================================================== APPELÉES PAR LE COEUR */

function printEqLogic(_eqLogic) {
  /* Le coeur ne réinitialise que les .eqLogicAttr : sans cela, le diagnostic
     garderait la réponse de l'appareil précédemment ouvert. */
  solplanetbeClear()
  solplanetbeStatus('', '')

  if (isset(_eqLogic.id) && _eqLogic.id !== '') {
    solplanetbeLoad(_eqLogic.id)
  }
}

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} }
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }

  var tr = '<td>'
  /* Sans ce champ, chaque enregistrement détruit puis recrée les commandes :
     l'historique est perdu et les scénarios pointent dans le vide. */
  tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>'
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom}}">'
  tr += '<span class="input-group-btn">'
  tr += '<a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a>'
  tr += '</span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '</td>'
  tr += '<td>'
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked>{{Afficher}}</label>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked>{{Historiser}}</label>'
  tr += '<span class="cmdAttr" data-l1key="htmlstate" style="display:inline-block;margin-left:5px;"></span>'
  tr += '</td>'
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a> '
  }
  tr += '</td>'

  /* Ligne créée en DOM : insertAdjacentHTML sur une table génère un <tbody> par
     insertion, et toutes les commandes se retrouveraient dans la même ligne. */
  var newRow = document.createElement('tr')
  newRow.innerHTML = tr
  newRow.classList.add('cmd')
  newRow.setAttribute('data-cmd_id', init(_cmd.id))
  newRow.setAttribute('title', '{{Identifiant interne}} : ' + init(_cmd.logicalId))
  document.getElementById('table_cmd').querySelector('tbody').appendChild(newRow)
  newRow.setJeeValues(_cmd, '.cmdAttr')
  /* L'ordre compte : changeType après setJeeValues, jamais l'inverse. */
  jeedom.cmd.changeType(newRow, init(_cmd.subType))
}

/* =============================================================== ÉCOUTEURS */

/* Les pages de plugin sont chargées en ajax : DOMContentLoaded a déjà eu lieu
   quand ce script s'exécute. Les écouteurs sont donc posés par délégation sur
   un conteneur qui, lui, existe déjà. */
var solplanetbeContainer = document.getElementById('div_pageContainer') || document.body

solplanetbeContainer.addEventListener('click', function (_event) {
  var target = _event.target
  if (target === null) { return }

  if (target.closest('#bt_solplanetbeDiscover') !== null) {
    _event.preventDefault()
    solplanetbeDiscover()
    return
  }
  if (target.closest('#bt_solplanetbeRefresh') !== null) {
    _event.preventDefault()
    solplanetbeRefresh()
    return
  }
  if (target.closest('#bt_solplanetbeHideHidden') !== null) {
    _event.preventDefault()
    var rows = document.querySelectorAll('#table_cmd tbody tr.cmd')
    for (var i = 0; i < rows.length; i++) {
      var box = rows[i].querySelector('.cmdAttr[data-l1key="isVisible"]')
      if (box !== null && !box.checked) {
        rows[i].style.display = (rows[i].style.display === 'none') ? '' : 'none'
      }
    }
  }
})
