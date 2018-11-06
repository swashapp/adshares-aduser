import json
import logging
from base64 import b64decode

from twisted.internet.protocol import Protocol
from twisted.internet import reactor, defer
from twisted.web.client import Agent

# http://ip-api.com/docs/api:serialized_php#usage_limits

logger = logging.getLogger(__name__)

schema_name = 'example_ipapi'
schema_version = '0.0.1'
schema = {'meta': {'name': schema_name,
                   'ver': schema_version},
          'values': {'countryCode':
                         {'label': 'Country',
                          'type': 'input'}}}

agent = Agent(reactor)

PIXEL_GIF = b64decode("R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")


class JsonProtocol(Protocol):
    def __init__(self, finished):
        self.finished = finished
        self.body = []

    def dataReceived(self, databytes):
        self.body.append(databytes)

    def connectionLost(self, reason):
        self.finished.callback(json.loads(''.join(self.body)))


def pixel(request):
    request.setHeader(b"content-type", b"image/gif")
    return PIXEL_GIF


def init():
    logger.info("IpApi initialized.")


@defer.inlineCallbacks
def update_data(user, request_data):

    url = 'http://ip-api.com/json/' + request_data['device']['ip']

    response = yield agent.request('GET', bytes(url))

    finished = defer.Deferred()
    response.deliverBody(JsonProtocol(finished))
    data = yield finished

    user['keywords'].update({'countryCode': data['countryCode']})

    defer.returnValue(user)


def normalize(data):
    return data
