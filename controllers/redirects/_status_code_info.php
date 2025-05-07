<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    <h4 class="modal-title"><?= e(trans('winter.redirect::lang.redirect.status_code')); ?></h4>
</div>

<div class="report-widget">
    <h3>301 (Moved Permanently)</h3>
    <p>The 301 (Moved Permanently) status code indicates that the target
        resource has been assigned a new permanent URI and any future
        references to this resource ought to use one of the enclosed URIs.
        Clients with link-editing capabilities ought to automatically re-link
        references to the effective request URI to one or more of the new
        references sent by the server, where possible.</p>

    <h3>302 (Found)</h3>
    <p>The 302 (Found) status code indicates that the target resource
        resides temporarily under a different URI. Since the redirection
        might be altered on occasion, the client ought to continue to use the
        effective request URI for future requests.
    </p>

    <h3>303 (See Other)</h3>
    <p>The 303 (See Other) status code indicates that the server is
        redirecting the user agent to a different resource, as indicated by a
        URI in the Location header field, which is intended to provide an
        indirect response to the original request. A user agent can perform
        a retrieval request targeting that URI (a GET or HEAD request if
        using HTTP), which might also be redirected, and present the eventual
        result as an answer to the original request. Note that the new URI
        in the Location header field is not considered equivalent to the
        effective request URI.</p>

    <h3>404 (Not Found)</h3>
    <p>The 404 (Not Found) status code indicates that the origin server did
        not find a current representation for the target resource or is not
        willing to disclose that one exists. A 404 status code does not
        indicate whether this lack of representation is temporary or
        permanent; the 410 (Gone) status code is preferred over 404 if the
        origin server knows, presumably through some configurable means, that
        the condition is likely to be permanent.</p>

    <h3>410 (Gone)</h3>
    <p>The 410 (Gone) status code indicates that access to the target
        resource is no longer available at the origin server and that this
        condition is likely to be permanent. If the origin server does not
        know, or has no facility to determine, whether or not the condition
        is permanent, the status code 404 (Not Found) ought to be used
        instead.</p>
</div>
