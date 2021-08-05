var modal_instance;

function shareViews() {
  let contactInfo = $("#contacts").select2('data')
  // tell to select a user
  if (contactInfo[0].id === "") {
    // $("#error").append("You must select a contact first");
    M.toast({html: 'You must select a contact first!', classes: 'toastError'});
    return false;
  }

  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ListSharableViews',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'JSON',
    data: JSON.stringify({
      target_user: contactInfo[0].id
    }),
    success: function(data) {
      let cvId;
      let userId;
      let locked;
      let consumed;
      let shared;
      
      $('#contact_views_modal_content').empty();
      let html = '<table><thead><tr><th>Name</th><th>Share/Remove</th><th>Lock/Unlock</th><th>Enable/Disable display</th></tr></thead><tbody>';
      
      $(data).each(function () {
        cvId = this.custom_view_id;
        userId = this.user_id;
        locked = this.locked;
        consumed = this.is_consumed;
        shared = this.is_share;
        console.log(this);

        html += `<tr><td>${cvId}</td>` +
          `<td>${buildShareButton(cvId, userId, shared)}</td>` +
          `<td>${buildLockButton(cvId, userId, locked)}</td>` + 
          `<td>${buildDisplayButton(cvId, userId, consumed)}</td></tr>`;
      });
      html += '</tbody></table>';

      buildModal('contact_views_modal');
      $('#contact_views_modal_content').append(html);
      triggerModal($('#contact_views_modal_trigger'), "#contact_views_modal");
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function getContact() {
  let contactInfo = $("#contacts").select2('data')
  // tell to select a user
  if (contactInfo[0].id === "") {
    M.toast({html: 'You must select a contact first!', classes: 'toastError'});
    return false;
  }

  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ListContactCustomViews',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      contact_id: contactInfo[0].id
    }),
    success: function (data) {
      if (data) {
        if (modal_instance === undefined) {
          buildModal('contact_views_modal');
        }
        appendDataToModal(data, "contact_views_modal_trigger");
        triggerModal($('#contact_views_modal_trigger'), "#contact_views_modal");
      }
    },
    error: function (error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function becomeOwner(el) {
  let cvId = parseInt(el.dataset.cvid);
  let cv_name = el.dataset.cvname
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=BecomeOwner',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId
    }),
    success: function() {
      addNewCvCard(cv_name, cvId)
      $("#btn_add_view_" + cvId).addClass("disabled");
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function buildModal (id) {
  if (modal_instance === undefined) {
    const elems = document.querySelectorAll('#' + id);
    modal_instance = M.Modal.init(elems);
  }
}

function triggerModal (element, href) {
    element.attr('href', href);
    element[0].click();
}

function appendDataToModal(data, id) {
  $('#contact_views_modal_content').empty()
  html = '<table><thead><tr><th>Name</th><th>Owner</th><th>Locked</th><th>Seize View</th></tr></thead><tbody>';

  $(data).each(function () {
    // lock design
    let lockIco = '<i class="material-icons" style="color:red">lock_outline</i>';
    if (this.locked === '0') {
      lockIco = '<i class="material-icons" style="color:green">lock_open</i>';
    }
    
    // owner design
    let owner_ico = '<i class="material-icons" style="opacity:25%">person_pin</i>';
    if (this.is_owner === '1') {
      owner_ico = '<i class="material-icons" style="opacity:100%">person_pin</i>';
    }

    let add_button = `<button id="btn_add_view_${this.custom_view_id}" class="btn-floating" data-cvid="${this.custom_view_id}" data-cvname="${this.name}" onClick="becomeOwner(this)"><i class="material-icons">add</i></button>`;
    if (this.already_owned) {
      add_button = `<button id="btn_add_view_${this.custom_view_id}" class="btn-floating disabled" data-cvid="${this.custom_view_id}" data-cvname="${this.name}"><i class="material-icons">add</i></button>`;
    }

    html += `<tr><td>${this.name}</td><td>${owner_ico}</td><td>${lockIco}</td><td>${add_button}</td></tr>`; //lock_open person_pin lock_outling
  });

  html += '</tbody></table>';
  $('#contact_views_modal_content').append(html);
}

function addNewCvCard(cv_name, cvId) {
  let html = `<div id="card_${cvId}" class="col s3">` +
    '<div class="col s12">' +
    '<div class="card blue-grey darken-1">' +
      '<div class="card-content white-text">' +
        `<span class="card-title">${cv_name}</span>` +
      '</div>' +
      '<div class="card-action">' +
        `<a href="#" data-cvid="${cvId}" data-cvname="${cv_name}" onClick="giveBackOwnership(this)">Give back ownership</a>` +
      '</div>' +
    '</div>' +
  '</div>' +
'</div>"';

  $("#seized_custom_views").prepend(html);
}

function removeCard(cvId) {
  $("#card_" + cvId).remove();
}

function giveBackOwnership(el) {
  let cvId = parseInt(el.dataset.cvid);
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=GiveBackOwnership',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId
    }),
    success: function() {
      removeCard(cvId)
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  })
}

function buildShareButton(cvId, userId, shared) {
  let shareIco = "screen_share";
  let shareIcoHtml = `<button id="share_button_${cvId}" data-position="top" data-cv="${cvId}" data-user="${userId}" onClick="shareToUser(this)" class="btn-floating tooltipped `;
  let tooltipMessage = 'data-tooltip="Share view"';
  if (shared !== null) {
    shareIco = "stop_screen_share";
    shareIcoHtml += "shared";
    tooltipMessage = 'data-tooltip="Withdraw view"'
  }

  return `${shareIcoHtml}" ${tooltipMessage}><i id="i_share_${cvId}" class="material-icons">${shareIco}</i></button>`;
}

function buildLockButton(cvId, userId, locked) {
  let lockIco = "lock_open";
  let lockIcoHtml = `<button id="lock_button_${cvId}" data-position="top" data-cv="${cvId}" data-user="${userId}" onClick="lockUserView(this)" class="btn-floating tooltipped `;
  let tooltipMessage = 'data-tooltip="Lock view"';
  if (locked === null) {
    tooltipMessage = 'data-tooltip="View not shared"';
    lockIcoHtml += 'disabled';
  } else if (locked === "0") {
    tooltipMessage = 'data-tooltip="Unlock view"';
    lockIco = "lock_outline";
  } else {
    lockIcoHtml += 'locked';
  }

  return  `${lockIcoHtml}" ${tooltipMessage}><i id="i_lock_${cvId}" class="material-icons">${lockIco}</i></button>`;
}

function buildDisplayButton(cvId, userId, consumed) {
  let displayIco = "visibility";
  let displayIcoHtml = `<button id="display_button_${cvId}" data-position="top" data-cv="${cvId}" data-user="${userId}" onClick="consumeUserView(this)" class="btn-floating tooltipped `;
  let tooltipMessage = 'data-tooltip="Display view"';
  if (consumed === "1") {
    displayIco = "visibility_off";
    displayIcoHtml += 'consumed';
    tooltipMessage = 'data-tooltip="Hide view"';
  } else if (consumed === null ) {
    displayIcoHtml += 'disabled';
  }
  
  return `${displayIcoHtml}" ${tooltipMessage}><i id="i_display_${cvId}" class="material-icons">${displayIco}</i></button>`;
}

function shareToUser(el) {
  const method = ($(el).hasClass("shared")) ? 'RemoveView' : 'AddView';
  const cvId = parseInt(el.dataset.cv);
  const userId =  parseInt(el.dataset.user);
  console.log(method);
  
  
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=' + method,
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId,
      user_id: userId
    }),
    success: function() {
      shareViews()
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function lockUserView(el) {
  if ($(el).hasClass("disabled")) {
    return false;
  }

  const toLock = ($(el).hasClass("locked")) ? 0 : 1;
  const cvId = parseInt(el.dataset.cv);
  const userId =  parseInt(el.dataset.user);
  
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=LockView',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId,
      user_id: userId,
      to_lock: toLock
    }),
    success: function() {
      if (toLock === 0) {;
        $("#lock_button_" + cvId).children("i").text("lock_open");
        $("#lock_button_" + cvId).removeClass("locked");
      } else {
        $("#lock_button_" + cvId).children("i").text("lock_outline");
        $("#lock_button_" + cvId).addClass("locked");
      }
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function consumeUserView(el) {
  if ($(el).hasClass("disabled")) {
    return false;
  }

  const toConsume = ($(el).hasClass("consumed")) ? 0 : 1;
  const cvId = parseInt(el.dataset.cv);
  const userId =  parseInt(el.dataset.user);
  
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ConsumeView',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId,
      user_id: userId,
      to_consume: toConsume
    }),
    success: function() {
      if (toConsume === 0) {;
        $("#display_button_" + cvId).children("i").text("visibility");
        $("#display_button_" + cvId).removeClass("consumed");
      } else {
        $("#display_button_" + cvId).children("i").text("visibility_off");
        $("#display_button_" + cvId).addClass("consumed");
      }
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

$(document).ready(function () {
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ListSeizedViews',
    type: 'GET',
    contentType: 'application/json',
    success: function(data) {
      $(data).each(function () {
        addNewCvCard(this.name, this.custom_view_id);
      });
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
});