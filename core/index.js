var modal_instance;

function shareViews() {
  let contactInfo = $("#contacts").select2('data')
  // tell to select a user
  if (contactInfo[0].id === "") {
    $("#error").append("You must select a contact first");
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
      $('#contact_views_modal_content').empty();
      let html = '<table><thead><tr><th>Name</th><th>Share/Remove</th><th>Lock/Unlock</th><th>Enable/Disable display</th></tr></thead><tbody>';
      console.log(data);
      let share_ico_html;
      let share_ico;
      let lock_ico_html;
      let lock_ico;
      let display_ico_html;
      let display_ico;
      let cv_id;
      let user_id;
      let locked;
      let consumed;
      // let share;

      $(data).each(function (index) {
        cv_id = data[index].custom_view_id;
        user_id = data[index].user_id;
        locked = data[index].locked;
        consumed = data[index].is_consumed;

        // share design
        share_ico = "stop_screen_share";
        share_ico_html = `<button id="share_button_${cv_id}" data-cv="${cv_id}" data-user="${user_id}" data-locked="${locked}" data-consumed="${consumed}" onClick="shareToUser(this)" class="btn-floating`;
        if (data[index].share !== null) {
          share_ico = "screen_share";
          share_ico_html += `" data-state="0"><i id="i_share_${cv_id}" class="material-icons">`;
        } else {
          share_ico_html += `" data-state="1"><i id="i_share_${cv_id}" class="material-icons">`;
        }
        share_ico_html += `${share_ico}</i></button>`;
        console.log(share_ico_html);

        // lock design
        lock_ico = "lock_outline";
        lock_ico_html = `<button id="lock_button_${cv_id}" data-cv="${cv_id}" data-user="${user_id}" data-locked="${locked}" data-consumed="${consumed}" onClick="shareToUser(this)" class="btn-floating `;
        if (data[index].locked === null) {
          lock_ico_html = ` class="btn-floating disabled" data-state="2"><i id="i_lock_${cv_id}" class="material-icons">`;
        } else if (data[index].locked === 0) {
          lock_ico = "lock_open";
          lock_ico_html = `<button id="lock_button_${cv_id}" class="btn-floating" data-state="1"><i id="i_lock_${cv_id}" class="material-icons">`;
        } else {
          lock_ico_html = `class="btn-floating" data-state="0"><i id="i_lock_${cv_id}" class="material-icons">`;
        }
        lock_ico_html = `${lock_ico_html}${lock_ico}</i></button>`;

        // display design
        display_ico = "visibility";
        display_ico_html = `<button id="display_button_${cv_id}" class="btn-floating" data-state="0"><i id="i_display_${cv_id}" class="material-icons">`;
        if (data[index].is_consumed === 1) {
          display_ico = "visibility_off";
          display_ico_html = `<button id="display_button_${cv_id}" class="btn-floating" data-state="1"><i id="i_display_${cv_id}" class="material-icons">`;
        } else if (data[index].is_consumed === null ) {
          display_ico_html = `<button id="display_button_${cv_id}" class="btn-floating disabled" data-state="2"><i id="i_display_${cv_id}" class="material-icons">`;
        }
        display_ico_html = `${display_ico_html}${display_ico}</i></button>`;

        html += `<tr><td>${cv_id}</td><td>${share_ico_html}</td><td>${lock_ico_html}</td><td>${display_ico_html}</td></tr>`; //lock_open person_pin lock_outling
        
      });
      html += '</tbody></table>';
      if (modal_instance === undefined) {
        buildModal('contact_views_modal');
      }
      $('#contact_views_modal_content').append(html);
      triggerModal($('#contact_views_modal_trigger'), "#contact_views_modal");
    },
    error: function(error) {
      console.log(error)
    }
  });
}

function getContact() {
    // remove old displayed data
  $("#error").empty();
  
  let contactInfo = $("#contacts").select2('data')
  // tell to select a user
  if (contactInfo[0].id === "") {
    $("#error").append("You must select a contact first");
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
      console.log(error);
    },
    fail: function(fail) {
      console.log(fail);
    }
  });
}



function becomeOwner(el) {
  let cv_id = parseInt(el.dataset.cv_id);
  let cv_name = el.dataset.cv_name
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=BecomeOwner',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cv_id
    }),
    success: function() {
      addNewCvCard(cv_name, cv_id)
      $("#btn_add_view_" + cv_id).addClass("disabled");
    },
    error: function(error) {
      console.log(error)
    }
  });
}

function buildModal (id) {
  const elems = document.querySelectorAll('#' + id);
  modal_instance = M.Modal.init(elems);
}

function triggerModal (element, href) {
    element.attr('href', href);
    element[0].click();
    // element.removeAttr('href');
    // element.removeClass('modal-trigger');
}

function appendDataToModal(data, id) {
  $('#contact_views_modal_content').empty()
  html = '<table><thead><tr><th>Name</th><th>Owner</th><th>Locked</th><th>Seize View</th></tr></thead><tbody>';

  $(data).each(function (index) {
    // lock design
    let lock_ico = '<i class="material-icons" style="color:red">lock_outline</i>';
    if (data[index].locked === '0') {
      lock_ico = '<i class="material-icons" style="color:green">lock_open</i>';
    }
    
    // owner design
    let owner_ico = '<i class="material-icons" style="opacity:25%">person_pin</i>';
    if (data[index].is_owner === '1') {
      owner_ico = '<i class="material-icons" style="opacity:100%">person_pin</i>';
    }

    let add_button = `<button id="btn_add_view_${data[index].custom_view_id}" class="btn-floating" data-cv_id="${data[index].custom_view_id}" data-cv_name="${data[index].name}" onClick="becomeOwner(this)"><i class="material-icons">add</i></button>`;
    if (data[index].already_owned) {
      add_button = `<button id="btn_add_view_${data[index].custom_view_id}" class="btn-floating disabled" data-cv_id="${data[index].custom_view_id}" data-cv_name="${data[index].name}"><i class="material-icons">add</i></button>`;
    }

    html += `<tr><td>${data[index].name}</td><td>${owner_ico}</td><td>${lock_ico}</td><td>${add_button}</td></tr>`; //lock_open person_pin lock_outling
  });

  html += '</tbody></table>';
  $('#contact_views_modal_content').append(html);
}

function addNewCvCard(cv_name, cv_id) {
  let html = `<div id="card_${cv_id}" class="row">` +
    '<div class="col s6">' +
    '<div class="card blue-grey darken-1">' +
      '<div class="card-content white-text">' +
        `<span class="card-title">${cv_name}</span>` +
      '</div>' +
      '<div class="card-action">' +
        `<a href="#" data-cv_id="${cv_id}" data-cv_name="${cv_name}" onClick="giveBackOwnership(this)">Give back ownership</a>` +
      '</div>' +
    '</div>' +
  '</div>' +
'</div>"';

  $("#seized_custom_views").prepend(html);
}

function removeCard(cv_id) {
  $("#card_" + cv_id).remove();
}


function giveBackOwnership(el) {
  let cv_id = parseInt(el.dataset.cv_id);
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=GiveBackOwnership',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cv_id
    }),
    success: function() {
      console.log(cv_id)
      removeCard(cv_id)
    },
    error: function(error) {
      console.log(error)
    }
  })
}

$(document).ready(function () {
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ListSeizedViews',
    type: 'GET',
    contentType: 'application/json',
    success: function(data) {
      console.log(data);
      $(data).each(function (index) {
        addNewCvCard(data[index].name, data[index].custom_view_id);
      });
    },
    error: function(error) {
      console.log(error)
    }
  });
});