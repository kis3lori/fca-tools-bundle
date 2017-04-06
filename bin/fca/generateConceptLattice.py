from gistfile1 import *
import sys
import json
import inspect

class SetEncoder(json.JSONEncoder):
    def default(self, obj):
        if inspect.isfunction(obj):
            return []
        if isinstance(obj, set):
            return list(obj)
        if isinstance(obj, lattice_node):
            return obj.__dict__
        if isinstance(obj, fca_lattice):
            return obj.__dict__
        return json.JSONEncoder.default(self, obj)

class LatticeEncoder(json.JSONEncoder):
    def default(self, obj):
        result = {}
        print obj.topnode
        print obj.topnode.__dict__
        result['top'] = {};
        result['level'] = obj.topnode.level
        
        return result

with open(sys.argv[1]) as data_file:    
    concepts = json.load(data_file)

lattice = fca_lattice(concepts['objects'], concepts['attributes'])
ld = lattice_diagram(lattice,400,400)

with open(sys.argv[2], 'w') as outfile:
    json.dump(ld.__dict__, outfile, cls=SetEncoder)

#display using tkinter
#ld.tkinter()
#mainloop()
#display using inkscape
#ld.svg().inkscape()
