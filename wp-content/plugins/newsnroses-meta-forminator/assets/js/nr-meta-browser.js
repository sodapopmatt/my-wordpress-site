(function ($) {

  var capturedEmail = '';

  $(document).on('before:forminator:form:submit', function (event, formData) {
    if (formData && typeof formData.get === 'function') {
      capturedEmail = formData.get('email-1') || '';
      console.log('NR email captured:', capturedEmail);
    }
  });

  $(document).on('forminator:form:submit:success', function (event, formId) {
    var eventId = 'nr_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);

    console.log('NR forminator success fired');
    console.log('NR browser eventID:', eventId);
    console.log('NR email at success:', capturedEmail);

    fetch('/wp-json/newsnroses/v1/meta-subscribe', {
      method: 'POST',
      keepalive: true,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nnMeta.nonce
      },
      body: JSON.stringify({
        event_id: eventId,
        form_id: formId,
        email: capturedEmail
      })
    }).then(function(res) {
      console.log('NR CAPI relay status:', res.status);
      return res.json();
    }).then(function(data) {
      console.log('NR CAPI relay response:', data);
    }).catch(function(err) {
      console.warn('NR CAPI relay failed:', err);
    });

    if (typeof fbq === 'function') {
      fbq('track', 'CompleteRegistration', {}, { eventID: eventId });
      console.log('NR browser pixel fired:', eventId);
    } else {
      console.warn('NR fbq not found');
    }

  });

})(jQuery);