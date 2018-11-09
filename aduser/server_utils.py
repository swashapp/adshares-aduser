import logging

from twisted.web.server import Site
from twisted.web.resource import Resource
from twisted.internet import reactor

from aduser import plugin, const
from aduser.server import PixelRequest, DataRequest, NormalizationRequest, SchemaRequest
import sys


class PixelFactory(Resource):
    """
    Routing class for pixels.
    """
    def getChild(self, path, request):  # NOSONAR
        return PixelRequest(path)


def configure_server():
    """
    Initialize the server.

    :return:
    """
    root = Resource()
    root.putChild("pixel", PixelFactory())
    root.putChild("getData", DataRequest())
    root.putChild("getSchema", SchemaRequest())
    root.putChild("normalize", NormalizationRequest())

    logger = logging.getLogger(__name__)
    logger.info("Initializing server.")
    logger.info("Configured with cookie name: '{0}' with expiration of {1}.".format(const.COOKIE_NAME,
                                                                                    const.EXPIRY_PERIOD))

    plugin.initialize(const.ADUSER_DATA_PROVIDER)

    if not plugin.data:
        logger.info("Failed to load data plugin, exiting.")
        sys.exit(1)

    return reactor.listenTCP(const.SERVER_PORT, Site(root))