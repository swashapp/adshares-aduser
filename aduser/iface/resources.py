import json
import logging

from twisted.internet import defer
from twisted.web.resource import NoResource, Resource
from twisted.web.server import NOT_DONE_YET

import aduser.data as data_backend
from aduser.db import utils as db_utils
from aduser.iface import const as iface_const
from aduser.utils import utils

#: This cache is never cleared! Please improve.
cache = {}


class PixelPathResource(Resource):
    """
    Routing class for pixel paths.
    """
    isLeaf = True

    @staticmethod
    def render_GET(request):  # NOSONAR
        request.setHeader(b"content-type", b"application/json")
        return '"http://{0}:{1}/{2}'.format(request.getHost().host,
                                            request.getHost().port,
                                            iface_const.PIXEL_PATH) + '/{adserver_id}/{user_id}/{nonce}.gif"'


class PixelFactory(Resource):
    """
    Router handler for endpoints of pixel requests. This is a `twisted.web.resource.Resource`.
    """

    def getChild(self, adserver_id, request):
        if adserver_id == '':
            return NoResource()
        return AdServerPixelFactory(adserver_id)


class AdServerPixelFactory(Resource):

    def __init__(self, adserver_id):
        Resource.__init__(self)
        self.adserver_id = adserver_id

    def getChild(self, user_id, request):
        if user_id == '':
            return NoResource()
        return UserPixelFactory(self.adserver_id, user_id)


class UserPixelFactory(Resource):
    def __init__(self, adserver_id, user_id):
        Resource.__init__(self)
        self.adserver_id = adserver_id
        self.user_id = user_id

    def getChild(self, nonce, request):
        if nonce == '':
            return NoResource()
        return UserPixelResource(self.adserver_id, self.user_id, nonce)


class UserPixelResource(Resource):

    isLeaf = True

    def __init__(self, adserver_id, user_id, nonce):
        Resource.__init__(self)
        self.adserver_id = adserver_id
        self.user_id = user_id
        self.nonce = nonce

    def render_GET(self, request):  # NOSONAR

        tid = utils.attach_tracking_cookie(request)
        db_utils.update_mapping({'tracking_id': tid,
                                 'server_user_id': self.adserver_id + '_' + self.user_id})
        logging.debug({'tracking_id': tid,
                       'server_user_id': self.adserver_id + '_' + self.user_id})

        db_utils.update_pixel({'tracking_id': tid,
                               'request': [h for h in request.requestHeaders.getAllRawHeaders()]})
        # Log request
        logger = logging.getLogger(__name__)
        logger.info({'tracking_id': tid,
                     'request': [h for h in request.requestHeaders.getAllRawHeaders()]})

        return data_backend.provider.pixel(request)


class DataResource(Resource):
    """
    Router handler for endpoints of data requests. This is a `twisted.web.resource.Resource`.
    """
    isLeaf = True

    def render_POST(self, request):  # NOSONAR
        self.handle_data(request)
        request.setHeader(b"content-type", b"application/json")
        return NOT_DONE_YET

    @defer.inlineCallbacks
    def handle_data(self, request):
        global cache

        logger = logging.getLogger(__name__)

        req_text = request.content.read()

        # Check cache
        if not iface_const.DEBUG_WITHOUT_CACHE and req_text in cache:
            yield request.write(cache[req_text])
            yield request.finish()
            return

        # Parse data from request
        try:
            post_data = json.loads(req_text)
        except ValueError:
            logger.debug('JSON parsing error (ValueError)')
            logger.debug(request.content.read())
            request.setResponseCode(400)
            request.finish()
            return

        # Validate request data
        try:
            request_data = {'site': {},
                            'device': {}}

            request_data['device']['ip'] = post_data['ip']
            request_data['device']['ua'] = post_data['ua']

            default_data = {'uid': post_data['uid'],
                            'human_score': 0.5,
                            'keywords': {}}

        # Missing fields in request
        except KeyError:
            logger.debug('Data invalid (KeyError)')

            request.setResponseCode(400)
            request.finish()
            return

        # Try to get mapping and user data from db
        try:
            user_map = yield db_utils.get_mapping(post_data['uid'])
            cached_data = yield db_utils.get_user_data(user_map['tracking_id'])

        except TypeError:
            logger.debug('User not found')

            request.setResponseCode(404)
            request.finish()
            return

        logger.debug('Cached data: {0}'.format(cached_data))

        # Update data with cached data
        if cached_data:
            default_data['keywords'] = cached_data['keywords']

        data = yield data_backend.provider.update_data(default_data, request_data)
        data.update({'tracking_id': user_map['tracking_id']})

        if cached_data:
            for k in ['keywords', 'human_score']:
                if data[k] != cached_data[k]:
                    yield db_utils.update_user_data(data)
                    break
        else:
            yield db_utils.update_user_data(data)

        # Remove tracking info from response
        del data['tracking_id']

        logger.debug('User data: {0}'.format(data))

        # Update cache and return data in JSON.
        json_data = json.dumps(data)
        cache[req_text] = json_data
        yield request.write(json_data)
        yield request.finish()


class TaxonomyResource(Resource):
    """
    Router handler for endpoints of schema requests. This is a `twisted.web.resource.Resource`.
    """
    isLeaf = True

    @staticmethod
    def render_GET(request):  # NOSONAR

        request.setHeader(b"content-type", b"application/json")
        return json.dumps(data_backend.provider.taxonomy)


class ApiInfoResource(Resource):
    """
    Router handler for normalization of targeting data. This is a `twisted.web.resource.Resource`.
    """
    isLeaf = True

    @staticmethod
    def render_GET(request):  # NOSONAR
        request.setHeader(b"content-type", b"application/json")
        return json.dumps({})